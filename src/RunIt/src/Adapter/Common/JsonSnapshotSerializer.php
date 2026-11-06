<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use Closure;
use ItPack\RunIt\Contract\Exception\SerializationException;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use JsonException;
use JsonSerializable;
use WeakMap;

/**
 * Default JSON serializer used by core snapshot recorders.
 *
 * It reduces arbitrary data to a recursively-primitive structure (arrays, scalars, and null)
 * using a deterministic JSON encoding, then decodes it back to primitives so that no PHP object
 * survives. In addition to the structural guarantees of the port, it enforces the
 * serialization security bounds from {@see SecurityLimits}:
 *
 *  - resources and closures are rejected explicitly (JSON would otherwise silently
 *    coerce a resource to `null` or emit the literal string `INF`/`NAN` for non-finite floats);
 *  - non-finite floats are rejected so a payload cannot carry a type-changing value;
 *  - a maximum normalization depth prevents runaway recursion;
 *  - invalid UTF-8 is rejected so the canonical form stays deterministic;
 *  - the encoded payload may not exceed a maximum byte size.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class JsonSnapshotSerializer implements SerializerInterface
{
    public function __construct(
        private SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function normalize(mixed $data): mixed
    {
        $this->assertSafe($data, 1);

        /** @var positive-int $maxDepth */
        $maxDepth = $this->limits->maxSerializationDepth;

        try {
            $encoded = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
                $maxDepth
            );
        } catch (JsonException $jsonException) {
            throw new SerializationException(
                'Unable to serialize snapshot payload: ' . $jsonException->getMessage(),
                0,
                $jsonException
            );
        }

        $bytes = strlen($encoded);

        if ($bytes > $this->limits->maxSerializationBytes) {
            throw new SerializationException(
                sprintf(
                    'Unable to serialize snapshot payload: maximum size of %d bytes, got %d.',
                    $this->limits->maxSerializationBytes,
                    $bytes
                ),
            );
        }

        try {
            return json_decode($encoded, associative: true, depth: $maxDepth, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw new SerializationException(
                'Unable to serialize snapshot payload: ' . $jsonException->getMessage(),
                0,
                $jsonException
            );
        }
    }

    public function contentType(): string
    {
        return MediaTypes::APPLICATION_JSON;
    }

    /**
     * Recursively rejects values that JSON cannot represent safely.
     *
     * The depth bound is enforced for both arrays and objects. Objects are
     * tracked in an ancestor set so a cyclic reference throws instead of
     * recursing until the stack overflows. Only {@see \JsonSerializable} objects
     * survive; every other object type is rejected.
     *
     * @param WeakMap<object, true>|null $ancestors ancestor set used to detect cyclic object references
     *
     * @throws SerializationException when $data contains a resource, closure,
     *                                unsupported object, non-finite float, a cycle,
     *                                or exceeds the depth bound
     */
    private function assertSafe(mixed $value, int $depth, ?WeakMap $ancestors = null): void
    {
        if (is_resource($value)) {
            throw new SerializationException('Unable to serialize snapshot payload: resource values are not allowed.');
        }

        if ($value instanceof Closure) {
            throw new SerializationException('Unable to serialize snapshot payload: closures are not allowed.');
        }

        if ($value instanceof JsonSerializable) {
            if ($depth > $this->limits->maxSerializationDepth) {
                throw new SerializationException(
                    sprintf(
                        'Unable to serialize snapshot payload: maximum depth %d exceeded.',
                        $this->limits->maxSerializationDepth
                    ),
                );
            }

            $ancestors ??= new WeakMap();

            if (isset($ancestors[$value])) {
                throw new SerializationException(
                    'Unable to serialize snapshot payload: cyclic object reference detected.'
                );
            }

            $ancestors[$value] = true;

            try {
                $this->assertSafe($value->jsonSerialize(), $depth + 1, $ancestors);
            } finally {
                unset($ancestors[$value]);
            }

            return;
        }

        if (is_object($value)) {
            throw new SerializationException(
                'Unable to serialize snapshot payload: unsupported object of type '
                . get_debug_type($value)
                . '.'
            );
        }

        if (is_float($value) && !is_finite($value)) {
            throw new SerializationException(
                'Unable to serialize snapshot payload: non-finite floats are not allowed.'
            );
        }

        if (is_array($value)) {
            if ($depth > $this->limits->maxSerializationDepth) {
                throw new SerializationException(
                    sprintf(
                        'Unable to serialize snapshot payload: maximum depth %d exceeded.',
                        $this->limits->maxSerializationDepth
                    ),
                );
            }

            foreach ($value as $member) {
                $this->assertSafe($member, $depth + 1, $ancestors);
            }
        }
    }
}
