<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

use ItPack\CommandIt\Contract\Exception\SerializationExceptionInterface;

/**
 * Port that normalizes arbitrary data into a safe, deterministically-serializable form.
 *
 * The domain hands arbitrary caller data to this port before it is recorded. The serializer
 * must return a recursively-primitive structure (no PHP objects, resources, or closures) so
 * that downstream encoding is explicit, deterministic, and free of arbitrary object
 * serialization.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SerializerInterface
{
    /**
     * Normalizes arbitrary data into a recursively-primitive, deterministically-serializable structure.
     *
     * Objects are reduced to their primitive representation; values that cannot be safely
     * represented (resources, closures, and similar) cause a {@see SerializationExceptionInterface}
     * to be thrown. The returned value contains no PHP objects.
     *
     * @throws SerializationExceptionInterface when $data cannot be safely represented
     */
    public function normalize(mixed $data): mixed;

    /**
     * The content type produced when the normalized output is encoded (for example application/json).
     */
    public function contentType(): string;
}
