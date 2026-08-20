<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Exception\SerializationException;
use ItPack\CommandIt\Contract\Exception\SerializationExceptionInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;

/**
 * Recursively normalizes arbitrary data into a deterministically-serializable,
 * object-free structure.
 *
 * Scalars and arrays pass through. Objects with a `toArray()` method are reduced
 * to their primitive form; {@see \Stringable} objects become strings. Resources,
 * closures, and any other object cause a {@see SerializationExceptionInterface}.
 *
 * @api
 */
final class SafeSerializer implements SerializerInterface
{
    public function normalize(mixed $data): mixed
    {
        return $this->normalizeValue($data);
    }

    private function normalizeValue(mixed $data): mixed
    {
        if ($data === null || \is_scalar($data)) {
            return $data;
        }

        if (\is_array($data)) {
            $out = [];

            foreach ($data as $key => $value) {
                $out[$key] = $this->normalizeValue($value);
            }

            return $out;
        }

        if (\is_object($data)) {
            if (\method_exists($data, 'toArray')) {
                /** @var mixed $asArray */
                $asArray = $data->toArray();

                return $this->normalizeValue($asArray);
            }

            if ($data instanceof \Stringable) {
                return (string) $data;
            }

            throw new SerializationException(\sprintf('Cannot safely normalize object of type %s.', \get_debug_type($data)));
        }

        throw new SerializationException('Resources and closures cannot be safely serialized.');
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
