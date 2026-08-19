<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Exception\SerializationException;
use ItPack\RunIt\Contract\Port\SerializerInterface;

/**
 * Default JSON serializer used by core snapshot recorders.
 *
 * It reduces arbitrary data to a recursively-primitive structure (arrays, scalars, and null)
 * using a deterministic JSON encoding, then decodes it back to primitives so that no PHP object
 * survives. Encoding uses fixed flags for stable output; values that JSON cannot represent
 * (resources, closures, and similar) raise {@see SerializationException}.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class JsonSnapshotSerializer implements SerializerInterface
{
    public function normalize(mixed $data): mixed
    {
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        if ($encoded === false) {
            throw new SerializationException('Unable to serialize snapshot payload: ' . json_last_error_msg());
        }

        return json_decode($encoded, true, 512, JSON_BIGINT_AS_STRING);
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
