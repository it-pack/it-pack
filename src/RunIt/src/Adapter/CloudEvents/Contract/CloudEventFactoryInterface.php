<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Contract;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;

/**
 * Builds a {@see CloudEvent} from its canonical field values.
 *
 * The factory is the low-level construction boundary that the occurrence mapper
 * composes. It centralizes validation and lets a consumer supply a different
 * CloudEvent representation without touching the domain mapping logic.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
interface CloudEventFactoryInterface
{
    /**
     * @param array<string, mixed> $extensions
     *
     * @throws InvalidCloudEventException when a field violates the envelope
     */
    public function create(
        string $id,
        string $source,
        string $type,
        mixed $data = null,
        ?string $subject = null,
        ?string $time = null,
        ?string $dataContentType = null,
        ?string $dataSchema = null,
        array $extensions = [],
        string $specVersion = CloudEvent::SPEC_VERSION_1_0,
    ): CloudEvent;
}
