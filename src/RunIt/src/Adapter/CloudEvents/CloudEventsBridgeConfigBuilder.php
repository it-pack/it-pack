<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;

/**
 * Immutable builder for {@see CloudEventsBridgeConfig}.
 *
 * A sink is mandatory; attempts to build without one fail fast. The mapping
 * policy and the occurrence mapper default to the built-in implementations.
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final class CloudEventsBridgeConfigBuilder
{
    private ?CloudEventSinkInterface $sink = null;

    private CloudEventMappingPolicy $policy;

    private ?OccurrenceToCloudEventMapperInterface $mapper = null;

    public function __construct()
    {
        $this->policy = CloudEventMappingPolicy::default();
    }

    public function withSink(CloudEventSinkInterface $sink): self
    {
        $this->sink = $sink;

        return $this;
    }

    public function withPolicy(CloudEventMappingPolicy $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    public function withMapper(OccurrenceToCloudEventMapperInterface $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    public function build(): CloudEventsBridgeConfig
    {
        if ($this->sink === null) {
            throw new \InvalidArgumentException('A CloudEvent sink is required to build the bridge config.');
        }

        return new CloudEventsBridgeConfig($this->sink, $this->policy, $this->mapper);
    }
}
