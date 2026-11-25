<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use InvalidArgumentException;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Contract\Adapter\CloudEvents\CloudEventsBridgeConfigBuilderInterface;

final class CloudEventsBridgeConfigBuilder implements CloudEventsBridgeConfigBuilderInterface
{
    private ?object $sink = null;

    private ?object $policy = null;

    private ?object $mapper = null;

    public function withSink(object $sink): self
    {
        $this->sink = $sink;

        return $this;
    }

    public function withPolicy(object $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    public function withMapper(object $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    public function build(): CloudEventsBridgeConfig
    {
        $sink = $this->sink;
        if (!$sink instanceof CloudEventSinkInterface) {
            throw new InvalidArgumentException('A CloudEvent sink is required to build the bridge config.');
        }

        $policy = $this->policy ?? CloudEventMappingPolicy::default();
        if (!$policy instanceof CloudEventMappingPolicy) {
            throw new InvalidArgumentException('A CloudEvent mapping policy is required.');
        }

        $mapper = $this->mapper;
        if ($mapper !== null && !$mapper instanceof OccurrenceToCloudEventMapperInterface) {
            throw new InvalidArgumentException('Invalid CloudEvent mapper.');
        }

        return new CloudEventsBridgeConfig($sink, $policy, $mapper);
    }
}
