<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\Behavior\LogEmitterBehavior;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\Duration;
use JsonException;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Trace\Span;

/**
 * Maps RunIt occurrences and snapshots to OpenTelemetry span events and logs.
 *
 * Occurrences are immutable facts; the bridge preserves their identity and type
 * and never exports large occurrence payloads as span attributes. Snapshots
 * export identity, type, and hash by default; the payload is exported only under
 * the explicit {@see SnapshotPayloadPolicy::Full} policy.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class DefaultOccurrenceLogMapper implements OccurrenceLogMapperInterface
{
    use LogEmitterBehavior;

    /**
     * @param list<string> $occurrenceLogAttributeAllowList extension-attribute keys exported to logs;
     * empty keeps the default unrestricted copy (bounded upstream by occurrence validation)
     */
    public function __construct(
        ?LoggerProviderInterface         $loggerProvider = null,
        private ?SnapshotPayloadPolicy    $snapshotPayloadPolicy = null,
        private bool                     $mapOccurrencesToLogs = false,
        private bool                     $mapOccurrencesToSpanEvents = false,
        private array                    $occurrenceLogAttributeAllowList = [],
    ) {
        $this->loggerProvider = $loggerProvider;
    }

    public function mapOccurrence(OccurrenceInterface $occurrence): void
    {
        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes */
        $attributes = [
            AttributeKeys::OCCURRENCE_ID => $occurrence->id()->toString(),
            AttributeKeys::OCCURRENCE_TYPE => $occurrence->type()->toString(),
            AttributeKeys::OCCURRENCE_SOURCE => $occurrence->source(),
            AttributeKeys::EXECUTION_ID => $occurrence->executionId()->toString(),
            AttributeKeys::OCCURRENCE_CONTENT_TYPE => $occurrence->contentType(),
        ];

        $subject = $occurrence->subject();
        if ($subject !== null) {
            $attributes[AttributeKeys::OCCURRENCE_SUBJECT] = $subject;
        }

        $activityId = $occurrence->activityId();
        if ($activityId instanceof StringIdentifierInterface) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId->toString();
        }

        foreach ($occurrence->extensionAttributes() as $key => $value) {
            if ($this->occurrenceLogAttributeAllowList !== []
                && !in_array($key, $this->occurrenceLogAttributeAllowList, strict: true)
            ) {
                continue;
            }

            $attributes[$key] = $value;
        }

        if ($this->mapOccurrencesToLogs) {
            $this->infoLogRecordBuilder()
                ->setTimestamp($occurrence->time()->epochSeconds() * Duration::NANOS_PER_SECOND)
                ->setBody($occurrence->type()->toString())
                ->setEventName('runit.occurrence')
                ->setAttributes($attributes)
                ->emit()
            ;
        }

        if ($this->mapOccurrencesToSpanEvents) {
            Span::getCurrent()->addEvent('runit.occurrence', $attributes);
        }
    }

    /**
     * @throws JsonException
     */
    public function mapSnapshot(SnapshotInterface $snapshot): void
    {
        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes */
        $attributes = [
            AttributeKeys::SNAPSHOT_ID => $snapshot->id()->toString(),
            AttributeKeys::SNAPSHOT_TYPE => $snapshot->type()->toString(),
            AttributeKeys::SNAPSHOT_CLASSIFICATION => $snapshot->classification()->label(),
            AttributeKeys::EXECUTION_ID => $snapshot->executionId()->toString(),
            AttributeKeys::SNAPSHOT_SCHEMA_VERSION => $snapshot->schemaVersion(),
            AttributeKeys::SNAPSHOT_CONTENT_TYPE => $snapshot->contentType(),
        ];

        $activityId = $snapshot->activityId();
        if ($activityId instanceof StringIdentifierInterface) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId->toString();
        }

        foreach ($snapshot->attributes() as $key => $value) {
            $attributes[$key] = $value;
        }

        if ($this->snapshotPayloadPolicy !== SnapshotPayloadPolicy::None) {
            $attributes[AttributeKeys::SNAPSHOT_HASH] = $snapshot->contentHash();
        }

        if ($this->snapshotPayloadPolicy === SnapshotPayloadPolicy::Full) {
            $attributes[AttributeKeys::SNAPSHOT_PAYLOAD] = json_encode(
                $snapshot->payload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        if ($this->mapOccurrencesToLogs) {
            $this->infoLogRecordBuilder()
                ->setTimestamp($snapshot->timestamp() * Duration::NANOS_PER_SECOND)
                ->setBody($snapshot->type()->toString())
                ->setEventName('runit.snapshot')
                ->setAttributes($attributes)
                ->emit()
            ;
        }

        if ($this->mapOccurrencesToSpanEvents) {
            Span::getCurrent()->addEvent('runit.snapshot', $attributes);
        }
    }

    public function mapRedactedOccurrence(array $normalized): void
    {
        $attributes = $this->buildRedactedOccurrenceAttributes($normalized);

        // The subject of a redacted occurrence is an unclassified, caller-controlled
        // string and is deliberately omitted from the suppressed delivery. Extension
        // attributes from redacted occurrences are intentionally not re-exported:
        // they may contain secrets. Unlike mapOccurrence(), mapRedactedOccurrence()
        // does not consult occurrenceLogAttributeAllowList, so redacted extension
        // attributes cannot be restored through that setting.
        // This is a breaking change versus previous versions that re-exported them.

        $this->emit(
            'runit.occurrence',
            is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized[ArtifactField::TYPE] : AttributeValues::REDACTED_TYPE,
            $attributes,
            is_int($normalized[ArtifactField::TIME] ?? null) ? $normalized[ArtifactField::TIME] : null
        );
    }

    public function mapRedactedSnapshot(array $normalized): void
    {
        $attributes = $this->getAttributes($normalized);

        $hash = $normalized[ArtifactField::CONTENT_HASH] ?? null;
        if ($this->snapshotPayloadPolicy !== SnapshotPayloadPolicy::None && is_string($hash)) {
            $attributes[AttributeKeys::SNAPSHOT_HASH] = $hash;
        }

        $this->emit(
            'runit.snapshot',
            is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized[ArtifactField::TYPE] : AttributeValues::REDACTED_TYPE,
            $attributes,
            is_int($normalized[ArtifactField::CREATED_AT] ?? null)
                ? $normalized[ArtifactField::CREATED_AT]
                : null
        );
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    public function getAttributes(array $normalized): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [
            AttributeKeys::SNAPSHOT_ID => is_string($normalized[ArtifactField::ID] ?? null)
                ? $normalized[ArtifactField::ID]
                : AttributeValues::REDACTED_ID,
            AttributeKeys::SNAPSHOT_TYPE => is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized[ArtifactField::TYPE]
                : AttributeValues::REDACTED_TYPE,
            AttributeKeys::SNAPSHOT_CLASSIFICATION => is_string($normalized[ArtifactField::CLASSIFICATION] ?? null)
                ? $normalized[ArtifactField::CLASSIFICATION]
                : AttributeValues::UNKNOWN_CLASSIFICATION,
            AttributeKeys::EXECUTION_ID => is_string($normalized[ArtifactField::EXECUTION_ID] ?? null)
                ? $normalized[ArtifactField::EXECUTION_ID]
                : AttributeValues::EMPTY_IDENTITY,
            AttributeKeys::REDACTED => AttributeValues::REDACTED_MARKER,
        ];

        if (is_string($normalized[ArtifactField::ACTIVITY_ID] ?? null)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $normalized[ArtifactField::ACTIVITY_ID];
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function emit(string $eventName, string $body, array $attributes, ?int $timeEpochSeconds): void
    {
        $timeNanos = $timeEpochSeconds !== null
            ? $timeEpochSeconds * Duration::NANOS_PER_SECOND
            : (int) (microtime(as_float: true) * Duration::NANOS_PER_SECOND);

        if ($this->mapOccurrencesToLogs) {
            $this->infoLogRecordBuilder()
                ->setTimestamp($timeNanos)
                ->setBody($body)
                ->setEventName($eventName)
                ->setAttributes($attributes)
                ->emit()
            ;
        }

        if ($this->mapOccurrencesToSpanEvents) {
            Span::getCurrent()->addEvent($eventName, $attributes);
        }
    }

    /**
     * Builds the occurrence-specific attribute set for a redacted occurrence
     * projection. Occurrence identity (id, type, source) and correlation fields
     * (execution, activity) are preserved, together with the redaction marker.
     * Extension attributes are intentionally not re-exported.
     *
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    private function buildRedactedOccurrenceAttributes(array $normalized): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [
            AttributeKeys::OCCURRENCE_ID => is_string($normalized[ArtifactField::ID] ?? null)
                ? $normalized[ArtifactField::ID]
                : AttributeValues::REDACTED_ID,
            AttributeKeys::OCCURRENCE_TYPE => is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized[ArtifactField::TYPE]
                : AttributeValues::REDACTED_TYPE,
            AttributeKeys::OCCURRENCE_SOURCE => is_string($normalized[ArtifactField::SOURCE] ?? null)
                ? $normalized[ArtifactField::SOURCE]
                : AttributeValues::REDACTED_SOURCE,
            AttributeKeys::EXECUTION_ID => is_string($normalized[ArtifactField::EXECUTION_ID] ?? null)
                ? $normalized[ArtifactField::EXECUTION_ID]
                : AttributeValues::EMPTY_IDENTITY,
            AttributeKeys::REDACTED => AttributeValues::REDACTED_MARKER,
        ];

        if (is_string($normalized[ArtifactField::ACTIVITY_ID] ?? null)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $normalized[ArtifactField::ACTIVITY_ID];
        }

        return $attributes;
    }
}
