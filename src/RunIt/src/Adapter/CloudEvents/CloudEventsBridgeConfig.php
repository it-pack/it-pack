<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;

/**
 * Immutable configuration for the RunIt CloudEvents bridge.
 *
 * The bridge never configures a global CloudEvents provider. The delivery target
 * is supplied as a {@see CloudEventSinkInterface}, and the mapping policy carries
 * the size and version bounds. A sink is required; the mapper defaults to the
 * built-in occurrence mapper unless explicitly overridden. Build instances with
 * {@see CloudEventsBridgeConfigBuilder}.
 *
 * @see https://cloudevents.io/
 */
final readonly class CloudEventsBridgeConfig
{
    public function __construct(
        public CloudEventSinkInterface                $sink,
        public CloudEventMappingPolicy                $policy = new CloudEventMappingPolicy(),
        public ?OccurrenceToCloudEventMapperInterface $mapper = null,
    ) {
    }

    public static function builder(): CloudEventsBridgeConfigBuilder
    {
        return new CloudEventsBridgeConfigBuilder();
    }
}
