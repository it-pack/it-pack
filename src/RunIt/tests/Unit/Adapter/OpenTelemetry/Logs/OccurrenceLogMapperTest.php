<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Logs;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.7 — Occurrences and snapshots to logs and span events.
 */
final class OccurrenceLogMapperTest extends Unit
{
    private OtelTestKit $kit;

    protected function _before(): void
    {
        $this->kit = new OtelTestKit();
    }

    public function testOccurrenceMapsToLogAndSpanEvent(): void
    {
        $spanExporter = new InMemoryExporter();
        $span = new TracerProvider(new SimpleSpanProcessor($spanExporter))
            ->getTracer('test')
            ->spanBuilder('current')
            ->startSpan()
        ;
        $scope = $span->activate();

        try {
            $mapper = new DefaultOccurrenceLogMapper(
                $this->kit->loggerProvider,
                SnapshotPayloadPolicy::HashOnly,
                mapOccurrencesToLogs: true,
                mapOccurrencesToSpanEvents: true
            );
            $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'execution.started.v1'));
        } finally {
            $scope->detach();
            $span->end();
        }

        $record = $this->kit->log(0);
        self::assertSame('execution.started.v1', $record->getBody());
        self::assertSame('runit.occurrence', $record->getEventName());
        self::assertSame('occ-1', $record->getAttributes()->get(AttributeKeys::OCCURRENCE_ID));
        self::assertSame('execution.started.v1', $record->getAttributes()->get(AttributeKeys::OCCURRENCE_TYPE));
        self::assertSame('it-pack/test', $record->getAttributes()->get(AttributeKeys::OCCURRENCE_SOURCE));

        $spans = $spanExporter->getSpans();
        /** @var SpanDataInterface $spanData */
        $spanData = $spans[0];
        $events = $spanData->getEvents();
        self::assertCount(1, $events);
        self::assertSame('runit.occurrence', $events[0]->getName());
    }

    public function testOccurrenceSkipsLoggingWhenDisabled(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: false,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-2', 'exec-1', 'execution.started.v1'));

        self::assertCount(0, $this->kit->logs());
    }

    public function testSnapshotExportsHashButNotPayloadByDefault(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['key' => str_repeat('x', 1024)]));

        $record = $this->kit->log(0);
        $attributes = $record->getAttributes()->toArray();
        self::assertArrayHasKey(AttributeKeys::SNAPSHOT_ID, $attributes);
        self::assertArrayHasKey(AttributeKeys::SNAPSHOT_HASH, $attributes);
        self::assertArrayNotHasKey(AttributeKeys::SNAPSHOT_PAYLOAD, $attributes);
    }

    public function testSnapshotPayloadExportIsOptIn(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::Full,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-2', 'exec-1', ['key' => 'value']));

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertArrayHasKey(AttributeKeys::SNAPSHOT_PAYLOAD, $attributes);
        self::assertArrayHasKey(AttributeKeys::SNAPSHOT_HASH, $attributes);
    }

    public function testSnapshotNonePolicyExportsNoHash(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::None,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-3', 'exec-1', ['key' => 'value']));

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertArrayNotHasKey(AttributeKeys::SNAPSHOT_HASH, $attributes);
        self::assertArrayNotHasKey(AttributeKeys::SNAPSHOT_PAYLOAD, $attributes);
    }

    public function testMissingLoggerProviderEmitsNothing(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            loggerProvider: null,
            snapshotPayloadPolicy: SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-3', 'exec-1', 'execution.started.v1'));
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-4', 'exec-1', ['key' => 'value']));

        self::assertCount(0, $this->kit->logs());
    }

    public function testOccurrenceExtensionAttributesAreCopiedByDefault(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-ext'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('execution.started.v1'),
            'it-pack/test',
            Instant::of(1_600),
            contentType: MediaTypes::APPLICATION_JSON,
            extensionAttributes: AttributeCollection::fromArray(['tenantflag' => 'on', 'otherflag' => 'off']),
        );

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapOccurrence($occurrence);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('on', $attributes['tenantflag']);
        self::assertSame('off', $attributes['otherflag']);
    }

    public function testOccurrenceLogAttributeAllowListRestrictsExportedExtensions(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-ext'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('execution.started.v1'),
            'it-pack/test',
            Instant::of(1_600),
            contentType: MediaTypes::APPLICATION_JSON,
            extensionAttributes: AttributeCollection::fromArray(['tenantflag' => 'on', 'cardinality_heavy' => 'x']),
        );

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
            occurrenceLogAttributeAllowList: ['tenantflag'],
        );
        $mapper->mapOccurrence($occurrence);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('on', $attributes['tenantflag'], 'listed keys pass the allow-list');
        self::assertArrayNotHasKey('cardinality_heavy', $attributes, 'unlisted keys are suppressed');
        self::assertSame(
            'occ-ext',
            $attributes[AttributeKeys::OCCURRENCE_ID],
            'structural identity attributes always export'
        );
    }

    public function testOccurrenceSubjectAndActivityAreExportedToLogAndSpanEvent(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-subj'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('execution.started.v1'),
            'it-pack/test',
            Instant::of(1_600),
            activityId: ActivityId::fromString('act-7'),
            subject: 'user-42',
            contentType: MediaTypes::APPLICATION_JSON,
            extensionAttributes: AttributeCollection::fromArray(['tenantflag' => 'on']),
        );

        $spanExporter = new InMemoryExporter();
        $span = new TracerProvider(new SimpleSpanProcessor($spanExporter))
            ->getTracer('test')
            ->spanBuilder('current')
            ->startSpan()
        ;
        $scope = $span->activate();

        try {
            $mapper = new DefaultOccurrenceLogMapper(
                $this->kit->loggerProvider,
                SnapshotPayloadPolicy::HashOnly,
                mapOccurrencesToLogs: true,
                mapOccurrencesToSpanEvents: true
            );
            $mapper->mapOccurrence($occurrence);
        } finally {
            $scope->detach();
            $span->end();
        }

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('user-42', $attributes[AttributeKeys::OCCURRENCE_SUBJECT]);
        self::assertSame('act-7', $attributes[AttributeKeys::ACTIVITY_ID]);
        self::assertSame('on', $attributes['tenantflag']);

        $spans = $spanExporter->getSpans();
        /** @var SpanDataInterface $spanData */
        $spanData = $spans[0];
        $events = $spanData->getEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertSame('user-42', $event->getAttributes()->get(AttributeKeys::OCCURRENCE_SUBJECT));
        self::assertSame('act-7', $event->getAttributes()->get(AttributeKeys::ACTIVITY_ID));
        self::assertSame('on', $event->getAttributes()->get('tenantflag'));
    }

    public function testRedactedOccurrenceUsesFallbacksForMissingFields(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapRedactedOccurrence([]);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame(AttributeValues::REDACTED_ID, $attributes[AttributeKeys::OCCURRENCE_ID]);
        self::assertSame(AttributeValues::REDACTED_TYPE, $attributes[AttributeKeys::OCCURRENCE_TYPE]);
        self::assertSame(AttributeValues::REDACTED_SOURCE, $attributes[AttributeKeys::OCCURRENCE_SOURCE]);
        self::assertSame(AttributeValues::EMPTY_IDENTITY, $attributes[AttributeKeys::EXECUTION_ID]);
        self::assertSame(AttributeValues::REDACTED_MARKER, $attributes[AttributeKeys::REDACTED]);
    }

    public function testRedactedOccurrenceExportsProvidedFieldsOnly(): void
    {
        $normalized = [
            'id' => 'occ-red',
            'type' => 'payment.captured.v1',
            'source' => 'test/source',
            'execution_id' => 'exec-9',
            'activity_id' => 'act-9',
            'extension_attributes' => ['tenantflag' => 'on'],
            'time' => 1_600,
        ];

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapRedactedOccurrence($normalized);

        $record = $this->kit->log(0);
        self::assertSame('payment.captured.v1', $record->getBody());
        self::assertSame(1_600_000_000_000, $record->getTimestamp());
        self::assertSame([
            AttributeKeys::OCCURRENCE_ID => 'occ-red',
            AttributeKeys::OCCURRENCE_TYPE => 'payment.captured.v1',
            AttributeKeys::OCCURRENCE_SOURCE => 'test/source',
            AttributeKeys::EXECUTION_ID => 'exec-9',
            AttributeKeys::REDACTED => AttributeValues::REDACTED_MARKER,
            AttributeKeys::ACTIVITY_ID => 'act-9',
        ], $record->getAttributes()->toArray());
    }

    public function testRedactedOccurrenceSpanEventOmitsExtensionAttributes(): void
    {
        $normalized = [
            'id' => 'occ-span-event',
            'type' => 'payment.captured.v1',
            'subject' => 'user-42',
            'activity_id' => 'act-9',
            'extension_attributes' => ['tenantflag' => 'on', 'secret' => 'do-not-export'],
            'time' => 1_600,
        ];

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: false,
            mapOccurrencesToSpanEvents: true
        );
        $span = $this->kit->tracerProvider->getTracer('test')->spanBuilder('current')->startSpan();
        $scope = $span->activate();
        try {
            $mapper->mapRedactedOccurrence($normalized);
        } finally {
            $scope->detach();
            $span->end();
        }

        $events = $this->kit->spans()[0]->getEvents();
        self::assertCount(1, $events);
        self::assertSame('runit.occurrence', $events[0]->getName());
        $eventAttributes = $events[0]->getAttributes()->toArray();
        self::assertSame('occ-span-event', $eventAttributes[AttributeKeys::OCCURRENCE_ID]);
        self::assertArrayNotHasKey(AttributeKeys::OCCURRENCE_SUBJECT, $eventAttributes);
        self::assertSame('act-9', $eventAttributes[AttributeKeys::ACTIVITY_ID]);
        self::assertArrayNotHasKey('tenantflag', $eventAttributes);
        self::assertArrayNotHasKey('secret', $eventAttributes);
    }

    public function testRedactedSnapshotFallsBackWhenFieldsAreMissing(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false
        );
        $mapper->mapRedactedSnapshot(['classification' => 'terminal', 'content_hash' => 'sha-256:abc']);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame(AttributeValues::REDACTED_ID, $attributes[AttributeKeys::SNAPSHOT_ID]);
        self::assertSame(AttributeValues::REDACTED_TYPE, $attributes[AttributeKeys::SNAPSHOT_TYPE]);
        self::assertSame('terminal', $attributes[AttributeKeys::SNAPSHOT_CLASSIFICATION]);
        self::assertSame('sha-256:abc', $attributes[AttributeKeys::SNAPSHOT_HASH]);
        self::assertSame(AttributeValues::REDACTED_MARKER, $attributes[AttributeKeys::REDACTED]);
    }

    public function testRedactedSnapshotMapsNormalizedFieldsWithoutPayload(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::Full,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $mapper->mapRedactedSnapshot([
            'id' => 'snap-red',
            'type' => 'execution.snapshot.v1',
            'classification' => 'terminal',
            'execution_id' => 'exec-9',
            'activity_id' => 'act-9',
            'content_hash' => 'sha-256:abc',
            'created_at' => 1700,
            'payload' => ['secret' => 'not exported'],
        ]);

        $record = $this->kit->log(0);
        self::assertSame('execution.snapshot.v1', $record->getBody());
        self::assertSame(1_700_000_000_000, $record->getTimestamp());
        self::assertSame([
            AttributeKeys::SNAPSHOT_ID => 'snap-red',
            AttributeKeys::SNAPSHOT_TYPE => 'execution.snapshot.v1',
            AttributeKeys::SNAPSHOT_CLASSIFICATION => 'terminal',
            AttributeKeys::EXECUTION_ID => 'exec-9',
            AttributeKeys::REDACTED => AttributeValues::REDACTED_MARKER,
            AttributeKeys::ACTIVITY_ID => 'act-9',
            AttributeKeys::SNAPSHOT_HASH => 'sha-256:abc',
        ], $record->getAttributes()->toArray());
    }

    public function testRedactedSnapshotUsesCreatedAtLikeTypedSnapshot(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $snapshot = RunItArtifactFactory::snapshot('snap-time', 'exec-1', ['key' => 'value']);
        $mapper->mapSnapshot($snapshot);
        $mapper->mapRedactedSnapshot(['created_at' => 1700, 'time' => 1600]);

        self::assertSame(1_700_000_000_000, $this->kit->log(0)->getTimestamp());
        self::assertSame($this->kit->log(0)->getTimestamp(), $this->kit->log(1)->getTimestamp());
    }

    public function testRedactedSnapshotUsesCurrentTimeForMissingOrInvalidCreatedAt(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        foreach ([[], ['created_at' => '1700'], ['created_at' => null]] as $normalized) {
            $before = (int) (microtime(true) * 1_000_000_000);
            $mapper->mapRedactedSnapshot($normalized + ['time' => 1600]);
            $after = (int) (microtime(true) * 1_000_000_000);
            $timestamp = $this->kit->log($this->kit->logs()->count() - 1)->getTimestamp();
            self::assertGreaterThanOrEqual($before, $timestamp);
            self::assertLessThanOrEqual($after, $timestamp);
        }
    }

    public function testSnapshotActivityAndAttributesAreExported(): void
    {
        $snapshot = RunItArtifactFactory::snapshot('snap-act', 'exec-1', ['key' => 'value']);

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::None,
            mapOccurrencesToLogs: true,
            mapOccurrencesToSpanEvents: false,
        );
        $mapper->mapSnapshot($snapshot);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('snap-act', $attributes[AttributeKeys::SNAPSHOT_ID]);
        self::assertArrayHasKey(AttributeKeys::SNAPSHOT_CLASSIFICATION, $attributes);
    }
}
