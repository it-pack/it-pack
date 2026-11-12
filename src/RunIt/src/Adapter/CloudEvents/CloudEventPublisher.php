<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventFactoryInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultCloudEventFactory;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Shared\ArtifactField;

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
 * @see https://cloudevents.io/
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final readonly class CloudEventPublisher implements ArtifactPublisherInterface
{
    public function __construct(
        private OccurrenceToCloudEventMapperInterface $mapper,
        private CloudEventSinkInterface               $sink,
        private CloudEventMappingPolicy               $policy = new CloudEventMappingPolicy(),
        private CloudEventFactoryInterface            $factory = new DefaultCloudEventFactory(),
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
        if ($artifact instanceof OccurrenceInterface) {
            $this->sink->send($this->mapper->map($artifact, $this->policy));

            return;
        }

        if (($artifact instanceof RedactedArtifactInterface || $artifact instanceof TrustedArtifactProjectionInterface)
            && $artifact->artifactType() === 'occurrence') {
            $this->sink->send($this->mapRedacted($artifact));

            return;
        }
    }

    /**
     * Emits a visible, non-sensitive "suppressed" CloudEvent for a redacted artifact.
     *
     * A redacted artifact has lost its concrete domain identity, so it can no longer be
     * mapped as an occurrence. Rather than silently dropping it, this builds a CloudEvent
     * that preserves only safe identity (id, type, source, subject, execution, sequence)
     * and declares `redacted=true`, so the delivery is observable without exposing any
     * sensitive data.
     */
    private function mapRedacted(ExecutionArtifactInterface $artifact): CloudEvent
    {
        $normalized = $artifact->normalize();

        $id = $this->asString($normalized[ArtifactField::ID] ?? null, 'redacted');
        $source = $this->asString($normalized['source'] ?? null, 'runit://redacted');
        $type = $this->asString($normalized[ArtifactField::TYPE] ?? null, 'runit.artifact.redacted');
        $subject = $this->asStringOrNull($normalized['subject'] ?? null);
        $time = $this->asIntOrNull($normalized['time'] ?? null);

        $extensions = [
            CloudEventExtensionKey::ExecutionId->value => $this->asString(
                $normalized[ArtifactField::EXECUTION_ID] ?? null,
                ''
            ),
            CloudEventExtensionKey::Sequence->value => $this->asInt($normalized['sequence'] ?? null),
            'redacted' => true,
        ];

        $activityId = $this->asStringOrNull($normalized[ArtifactField::ACTIVITY_ID] ?? null);
        if ($activityId !== null) {
            $extensions[CloudEventExtensionKey::ActivityId->value] = $activityId;
        }

        return $this->factory->create(
            $id,
            $source,
            $type,
            [],
            $subject,
            $time !== null ? gmdate('Y-m-d\TH:i:s\Z', $time) : null,
            MediaTypes::APPLICATION_JSON,
            null,
            $extensions,
            $this->policy->specVersion,
        );
    }

    private function asString(mixed $value, string $fallback): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $fallback;
    }

    private function asStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function asInt(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private function asIntOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
