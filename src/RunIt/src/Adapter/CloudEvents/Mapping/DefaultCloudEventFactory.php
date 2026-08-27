<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Mapping;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventFactoryInterface;

/**
 * Default {@see CloudEventFactoryInterface} backed by the immutable CloudEvent value object.
 *
 * Construction delegates to {@see CloudEvent::create}, so every produced event
 * is validated against the CloudEvents envelope rules. The factory is the single
 * place the bridge materializes a CloudEvent from field values.
 *
 * @see https://cloudevents.io/
 */
final class DefaultCloudEventFactory implements CloudEventFactoryInterface
{
    /**
     * @param array<string, mixed> $extensions
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
    ): CloudEvent {
        return CloudEvent::create(
            $id,
            $source,
            $type,
            $data,
            $subject,
            $time,
            $dataContentType,
            $dataSchema,
            $extensions,
            $specVersion,
        );
    }
}
