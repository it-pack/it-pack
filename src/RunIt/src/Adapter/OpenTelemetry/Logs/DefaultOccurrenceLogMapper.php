<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
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
final class DefaultOccurrenceLogMapper implements OccurrenceLogMapperInterface
{
    public function __construct(
        private readonly ?LoggerProviderInterface $loggerProvider,
        private readonly SnapshotPayloadPolicy $snapshotPayloadPolicy,
        private readonly bool $mapOccurrencesToLogs,
        private readonly bool $mapOccurrencesToSpanEvents,
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
        if ($activityId !== null) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId->toString();
        }

        foreach ($occurrence->extensionAttributes() as $key => $value) {
            $attributes[$key] = $value;
        }

        if ($this->mapOccurrencesToLogs && $this->loggerProvider !== null) {
            $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);
            $logger
                ->logRecordBuilder()
                ->setTimestamp($occurrence->time()->epochSeconds() * 1_000_000_000)
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
        if ($activityId !== null) {
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

        if ($this->mapOccurrencesToLogs && $this->loggerProvider !== null) {
            $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);
            $logger
                ->logRecordBuilder()
                ->setTimestamp($snapshot->timestamp() * 1_000_000_000)
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
            AttributeKeys::OCCURRENCE_ID => is_string($normalized['id'] ?? null) ? $normalized['id'] : 'redacted',
            AttributeKeys::OCCURRENCE_TYPE => is_string($normalized['type'] ?? null) ? $normalized['type'] : 'runit.artifact.redacted',
            AttributeKeys::OCCURRENCE_SOURCE => is_string($normalized['source'] ?? null) ? $normalized['source'] : 'runit://redacted',
            AttributeKeys::EXECUTION_ID => is_string($normalized['execution_id'] ?? null) ? $normalized['execution_id'] : '',
            'runit.redacted' => true,
        ];

        $subject = $normalized['subject'] ?? null;
        if (is_string($subject)) {
            $attributes[AttributeKeys::OCCURRENCE_SUBJECT] = $subject;
        }

        $activityId = $normalized['activity_id'] ?? null;
        if (is_string($activityId)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        /** @var array<string, mixed> $extensions */
        $extensions = is_array($normalized['extension_attributes'] ?? null) ? $normalized['extension_attributes'] : [];
        foreach ($extensions as $key => $value) {
            $attributes[$key] = $value;
        }

        $this->emit('runit.occurrence', is_string($normalized['type'] ?? null) ? $normalized['type'] : 'runit.artifact.redacted', $attributes, is_int($normalized['time'] ?? null) ? $normalized['time'] : null);
    }

    public function mapRedactedSnapshot(array $normalized): void
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [
            AttributeKeys::SNAPSHOT_ID => is_string($normalized['id'] ?? null) ? $normalized['id'] : 'redacted',
            AttributeKeys::SNAPSHOT_TYPE => is_string($normalized['type'] ?? null) ? $normalized['type'] : 'runit.artifact.redacted',
            AttributeKeys::SNAPSHOT_CLASSIFICATION => is_string($normalized['classification'] ?? null) ? $normalized['classification'] : 'unknown',
            AttributeKeys::EXECUTION_ID => is_string($normalized['execution_id'] ?? null) ? $normalized['execution_id'] : '',
            'runit.redacted' => true,
        ];

        $activityId = $normalized['activity_id'] ?? null;
        if (is_string($activityId)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        $hash = $normalized['content_hash'] ?? null;
        if (is_string($hash)) {
            $attributes[AttributeKeys::SNAPSHOT_HASH] = $hash;
        }

        $this->emit('runit.snapshot', is_string($normalized['type'] ?? null) ? $normalized['type'] : 'runit.artifact.redacted', $attributes, is_int($normalized['time'] ?? null) ? $normalized['time'] : null);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function emit(string $eventName, string $body, array $attributes, ?int $timeEpochSeconds): void
    {
        $timeNanos = $timeEpochSeconds !== null ? $timeEpochSeconds * 1_000_000_000 : (int) (microtime(true) * 1_000_000_000);

        if ($this->mapOccurrencesToLogs && $this->loggerProvider !== null) {
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
