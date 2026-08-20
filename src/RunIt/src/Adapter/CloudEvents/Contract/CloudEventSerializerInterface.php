<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Contract;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;

/**
 * Serializes a {@see CloudEvent} to and from its structured JSON representation.
 *
 * The structured mapping places the standard CloudEvents attributes and any
 * extension attributes as siblings at the top level of a single JSON object. The
 * serializer enforces the configured maximum serialized size and rejects
 * malformed payloads rather than attempting to recover them.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
interface CloudEventSerializerInterface
{
    /**
     * @throws InvalidCloudEventException when the serialized form exceeds the configured size
     */
    public function serialize(CloudEvent $event): string;

    /**
     * @throws InvalidCloudEventException when the payload is not valid JSON or is missing required fields
     */
    public function deserialize(string $payload): CloudEvent;
}
