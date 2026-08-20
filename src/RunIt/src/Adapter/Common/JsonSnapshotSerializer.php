<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use Closure;
use ItPack\RunIt\Contract\Exception\SerializationException;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

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
final class JsonSnapshotSerializer implements SerializerInterface
{
    public function __construct(
        private readonly SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function normalize(mixed $data): mixed
    {
        $this->assertSafe($data, 1);

        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            512,
        );

        if ($encoded === false) {
            throw new SerializationException('Unable to serialize snapshot payload: ' . json_last_error_msg());
        }

        $bytes = strlen($encoded);

        if ($bytes > $this->limits->maxSerializationBytes) {
            throw new SerializationException(
                sprintf('Snapshot payload exceeds maximum size of %d bytes, got %d.', $this->limits->maxSerializationBytes, $bytes),
            );
        }

        return json_decode($encoded, true, 512, JSON_BIGINT_AS_STRING);
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * Recursively rejects values that JSON cannot represent safely.
     *
     * @throws SerializationException when $data contains a resource, closure,
     *                                non-finite float, or exceeds the depth bound
     */
    private function assertSafe(mixed $value, int $depth): void
    {
        if (is_resource($value)) {
            throw new SerializationException('Unable to serialize snapshot payload: resource values are not allowed.');
        }

        if ($value instanceof Closure) {
            throw new SerializationException('Unable to serialize snapshot payload: closures are not allowed.');
        }

        if (is_object($value)) {
            if (!($value instanceof \JsonSerializable)) {
                throw new SerializationException('Unable to serialize snapshot payload: unsupported object of type ' . get_debug_type($value) . '.');
            }

            $this->assertSafe($value->jsonSerialize(), $depth);

            return;
        }

        if (is_float($value) && !is_finite($value)) {
            throw new SerializationException('Unable to serialize snapshot payload: non-finite floats are not allowed.');
        }

        if (is_array($value)) {
            if ($depth > $this->limits->maxSerializationDepth) {
                throw new SerializationException(
                    sprintf('Unable to serialize snapshot payload: maximum depth %d exceeded.', $this->limits->maxSerializationDepth),
                );
            }

            foreach ($value as $member) {
                $this->assertSafe($member, $depth + 1);
            }
        }
    }
}
