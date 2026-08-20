<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * RunIt publisher that maps occurrences to CloudEvents and delivers them.
 *
 * The publisher is the single entry point that adapts RunIt occurrences to
 * CloudEvents. It receives every published artifact and, for occurrences only,
 * maps the artifact to a CloudEvent and hands it to the configured sink. Other
 * artifact types are ignored, so the publisher can sit inside the publication
 * pipeline without affecting non-occurrence signals and without depending on any
 * external CloudEvents SDK.
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final class CloudEventPublisher implements ArtifactPublisherInterface
{
    public function __construct(
        private readonly OccurrenceToCloudEventMapperInterface $mapper,
        private readonly CloudEventSinkInterface $sink,
        private readonly CloudEventMappingPolicy $policy = new CloudEventMappingPolicy(),
    ) {
    }

    public static function fromConfig(CloudEventsBridgeConfig $config): self
    {
        return new self(
            $config->mapper ?? new DefaultOccurrenceToCloudEventMapper(),
            $config->sink,
            $config->policy,
        );
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (!$artifact instanceof OccurrenceInterface) {
            return;
        }

        $this->sink->send($this->mapper->map($artifact, $this->policy));
    }
}
