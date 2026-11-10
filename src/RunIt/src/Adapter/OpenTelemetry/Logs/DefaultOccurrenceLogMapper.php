<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\Severity;
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
    /**
     * @param list<string> $occurrenceLogAttributeAllowList extension-attribute keys exported to
     *                                                     logs; empty keeps the default unrestricted copy (bounded upstream by occurrence validation)
     */
    public function __construct(
        private ?LoggerProviderInterface $loggerProvider,
        private SnapshotPayloadPolicy    $snapshotPayloadPolicy,
        private bool                     $mapOccurrencesToLogs,
        private bool                     $mapOccurrencesToSpanEvents,
        private array                    $occurrenceLogAttributeAllowList = [],
    ) {
    }

    public function mapOccurrence(OccurrenceInterface $occurrence): void
    {
        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes */
        $attributes = [
            AttributeKeys::OCCURRENCE_ID => $occurrence->id()->toString(),
            AttributeKeys::OCCURRENCE_TYPE => $occurrence->type()->toString(),
            AttributeKeys::OCCURRENCE_SOURCE => $occurrence->source(),
            AttributeKeys::EXECUTION_ID => $occurrence->executionId()->toString(),
            'runit.occurrence.content_type' => $occurrence->contentType(),
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

        if ($this->mapOccurrencesToLogs && $this->loggerProvider instanceof LoggerProviderInterface) {
            $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);
            $logger
                ->logRecordBuilder()
                ->setTimestamp($occurrence->time()->epochSeconds() * Duration::NANOS_PER_SECOND)
                ->setSeverityNumber(Severity::INFO)
                ->setSeverityText('info')
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

    public function mapSnapshot(SnapshotInterface $snapshot): void
    {
        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes */
        $attributes = [
            AttributeKeys::SNAPSHOT_ID => $snapshot->id()->toString(),
            AttributeKeys::SNAPSHOT_TYPE => $snapshot->type()->toString(),
            AttributeKeys::SNAPSHOT_CLASSIFICATION => $snapshot->classification()->label(),
            AttributeKeys::EXECUTION_ID => $snapshot->executionId()->toString(),
            'runit.snapshot.schema_version' => $snapshot->schemaVersion(),
            'runit.snapshot.content_type' => $snapshot->contentType(),
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
            $attributes['runit.snapshot.payload'] = (string) json_encode(
                $snapshot->payload(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        if ($this->mapOccurrencesToLogs && $this->loggerProvider instanceof LoggerProviderInterface) {
            $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);
            $logger
                ->logRecordBuilder()
                ->setTimestamp($snapshot->timestamp() * Duration::NANOS_PER_SECOND)
                ->setSeverityNumber(Severity::INFO)
                ->setSeverityText('info')
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
        /** @var array<string, mixed> $attributes */
        $attributes = [
            AttributeKeys::OCCURRENCE_ID => is_string($normalized[ArtifactField::ID] ?? null)
                ? $normalized[ArtifactField::ID]
                : 'redacted',
            AttributeKeys::OCCURRENCE_TYPE => is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized['type']
                : 'runit.artifact.redacted',
            AttributeKeys::OCCURRENCE_SOURCE => is_string($normalized['source'] ?? null)
                ? $normalized['source']
                : 'runit://redacted',
            AttributeKeys::EXECUTION_ID => is_string($normalized[ArtifactField::EXECUTION_ID] ?? null)
                ? $normalized['execution_id']
                : '',
            'runit.redacted' => true,
        ];

        $subject = $normalized['subject'] ?? null;
        if (is_string($subject)) {
            $attributes[AttributeKeys::OCCURRENCE_SUBJECT] = $subject;
        }

        $activityId = $normalized[ArtifactField::ACTIVITY_ID] ?? null;
        if (is_string($activityId)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        // Extension attributes from redacted occurrences are intentionally not
        // re-exported: they may contain secrets, and export is governed by the
        // configured occurrenceLogAttributeAllowList instead of upstream redaction.
        // This is a breaking change versus previous versions that re-exported them;
        // reconfigure the allow-list if those fields are needed.

        $this->emit(
            'runit.occurrence',
            is_string($normalized[ArtifactField::TYPE] ?? null) ? $normalized['type'] : 'runit.artifact.redacted',
            $attributes,
            is_int($normalized[ArtifactField::TIME] ?? null) ? $normalized['time'] : null
        );
    }

    public function mapRedactedSnapshot(array $normalized): void
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [
            AttributeKeys::SNAPSHOT_ID => is_string($normalized[ArtifactField::ID] ?? null)
                ? $normalized[ArtifactField::ID]
                : 'redacted',
            AttributeKeys::SNAPSHOT_TYPE => is_string($normalized[ArtifactField::TYPE] ?? null)
                ? $normalized['type']
                : 'runit.artifact.redacted',
            AttributeKeys::SNAPSHOT_CLASSIFICATION => is_string($normalized['classification'] ?? null)
                ? $normalized['classification']
                : 'unknown',
            AttributeKeys::EXECUTION_ID => is_string($normalized[ArtifactField::EXECUTION_ID] ?? null)
                ? $normalized['execution_id']
                : '',
            'runit.redacted' => true,
        ];

        $activityId = $normalized[ArtifactField::ACTIVITY_ID] ?? null;
        if (is_string($activityId)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        $hash = $normalized['content_hash'] ?? null;
        if (is_string($hash)) {
            $attributes[AttributeKeys::SNAPSHOT_HASH] = $hash;
        }

        $this->emit(
            'runit.snapshot',
            is_string($normalized[ArtifactField::TYPE] ?? null) ? $normalized['type'] : 'runit.artifact.redacted',
            $attributes,
            is_int($normalized[ArtifactField::TIME] ?? null) ? $normalized['time'] : null
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function emit(string $eventName, string $body, array $attributes, ?int $timeEpochSeconds): void
    {
        $timeNanos = $timeEpochSeconds !== null
            ? $timeEpochSeconds * Duration::NANOS_PER_SECOND
            : (int) (microtime(as_float: true) * Duration::NANOS_PER_SECOND);

        if ($this->mapOccurrencesToLogs && $this->loggerProvider instanceof LoggerProviderInterface) {
            $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);
            $logger
                ->logRecordBuilder()
                ->setTimestamp($timeNanos)
                ->setSeverityNumber(Severity::INFO)
                ->setSeverityText('info')
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
}
