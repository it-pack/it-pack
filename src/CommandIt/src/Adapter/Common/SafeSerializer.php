<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Exception\SerializationException;
use ItPack\CommandIt\Contract\Exception\SerializationExceptionInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;
use SplObjectStorage;

/**
 * Recursively normalizes arbitrary data into a deterministically-serializable,
 * object-free structure.
 *
 * Scalars and arrays pass through. Objects with a `toArray()` method are reduced
 * to their primitive form; {@see \Stringable} objects become strings. Resources,
 * closures, and any other object cause a {@see SerializationExceptionInterface}.
 *
 * Two hostile-input shapes are rejected rather than looping forever: a repeated
 * object (detected via a {@see SplObjectStorage} visited-set) or a self-referencing
 * array throws {@see SerializationExceptionInterface} via {@see SerializationException::cyclic()},
 * and nesting deeper than {@see self::DEFAULT_MAX_DEPTH} throws
 * {@see SerializationException::tooDeep()}.
 *
 * @api
 */
final readonly class SafeSerializer implements SerializerInterface
{
    /**
     * Maximum recursion depth before {@see SerializationException::tooDeep()} is thrown.
     */
    public const DEFAULT_MAX_DEPTH = 32;

    public function __construct(
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
    }

    public function normalize(mixed $data): mixed
    {
        return $this->normalizeValue($data, new SplObjectStorage(), 0);
    }

    /**
     * @param SplObjectStorage<object, mixed> $seen Objects already on the current recursion path.
     */
    private function normalizeValue(mixed $data, SplObjectStorage $seen, int $depth): mixed
    {
        if ($data === null || \is_scalar($data)) {
            return $data;
        }

        if (\is_array($data)) {
            if ($depth > $this->maxDepth) {
                throw SerializationException::tooDeep($this->maxDepth);
            }

            $out = [];

            foreach ($data as $key => $value) {
                if (\is_array($value) && $value === $data) {
                    throw SerializationException::cyclic();
                }

                $out[$key] = $this->normalizeValue($value, $seen, $depth + 1);
            }

            return $out;
        }

        if (\is_object($data)) {
            if ($seen->contains($data)) {
                throw SerializationException::cyclic();
            }

            $seen->attach($data);

            try {
                if (\method_exists($data, 'toArray')) {
                    /** @var mixed $asArray */
                    $asArray = $data->toArray();

                    return $this->normalizeValue($asArray, $seen, $depth + 1);
                }

                if ($data instanceof \Stringable) {
                    return (string) $data;
                }

                throw new SerializationException(\sprintf('Cannot safely normalize object of type %s.', \get_debug_type($data)));
            } finally {
                $seen->detach($data);
            }
        }

        throw new SerializationException('Resources and closures cannot be safely serialized.');
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
