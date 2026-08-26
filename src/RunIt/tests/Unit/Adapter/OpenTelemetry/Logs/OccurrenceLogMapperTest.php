<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Logs;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
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
                true,
                true
            );
            $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'execution.started.v1'));
        } finally {
            $scope->detach();
            $span->end();
        }

        $record = $this->kit->log(0);
        self::assertSame('execution.started.v1', $record->getBody());
        self::assertSame('runit.occurrence', $record->getEventName());
        self::assertSame('occ-1', $record->getAttributes()->get('runit.occurrence.id'));
        self::assertSame('execution.started.v1', $record->getAttributes()->get('runit.occurrence.type'));
        self::assertSame('it-pack/test', $record->getAttributes()->get('runit.occurrence.source'));

        $spans = $spanExporter->getSpans();
        /** @var \OpenTelemetry\SDK\Trace\SpanDataInterface $spanData */
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
            false,
            false
        );
        $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-2', 'exec-1', 'execution.started.v1'));

        self::assertCount(0, $this->kit->logs());
    }

    public function testSnapshotExportsHashButNotPayloadByDefault(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false
        );
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['key' => str_repeat('x', 1024)]));

        $record = $this->kit->log(0);
        $attributes = $record->getAttributes()->toArray();
        self::assertArrayHasKey('runit.snapshot.id', $attributes);
        self::assertArrayHasKey('runit.snapshot.hash', $attributes);
        self::assertArrayNotHasKey('runit.snapshot.payload', $attributes);
    }

    public function testSnapshotPayloadExportIsOptIn(): void
    {
        $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::Full, true, false);
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-2', 'exec-1', ['key' => 'value']));

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertArrayHasKey('runit.snapshot.payload', $attributes);
        self::assertArrayHasKey('runit.snapshot.hash', $attributes);
    }

    public function testSnapshotNonePolicyExportsNoHash(): void
    {
        $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::None, true, false);
        $mapper->mapSnapshot(RunItArtifactFactory::snapshot('snap-3', 'exec-1', ['key' => 'value']));

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertArrayNotHasKey('runit.snapshot.hash', $attributes);
        self::assertArrayNotHasKey('runit.snapshot.payload', $attributes);
    }

    public function testMissingLoggerProviderEmitsNothing(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(null, SnapshotPayloadPolicy::HashOnly, true, false);
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
            null,
            null,
            null,
            null,
            MediaTypes::APPLICATION_JSON,
            AttributeCollection::fromArray(['tenantflag' => 'on', 'otherflag' => 'off']),
        );

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false
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
            null,
            null,
            null,
            null,
            MediaTypes::APPLICATION_JSON,
            AttributeCollection::fromArray(['tenantflag' => 'on', 'cardinality_heavy' => 'x']),
        );

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false,
            ['tenantflag'],
        );
        $mapper->mapOccurrence($occurrence);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('on', $attributes['tenantflag'], 'listed keys pass the allow-list');
        self::assertArrayNotHasKey('cardinality_heavy', $attributes, 'unlisted keys are suppressed');
        self::assertSame('occ-ext', $attributes['runit.occurrence.id'], 'structural identity attributes always export');
    }

    public function testOccurrenceSubjectAndActivityAreExportedToLogAndSpanEvent(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-subj'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('execution.started.v1'),
            'it-pack/test',
            Instant::of(1_600),
            null,
            \ItPack\RunIt\Domain\Activity\ActivityId::fromString('act-7'),
            'user-42',
            null,
            MediaTypes::APPLICATION_JSON,
            AttributeCollection::fromArray(['tenantflag' => 'on']),
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
                true,
                true
            );
            $mapper->mapOccurrence($occurrence);
        } finally {
            $scope->detach();
            $span->end();
        }

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('user-42', $attributes['runit.occurrence.subject']);
        self::assertSame('act-7', $attributes['runit.activity.id']);
        self::assertSame('on', $attributes['tenantflag']);

        $spans = $spanExporter->getSpans();
        /** @var \OpenTelemetry\SDK\Trace\SpanDataInterface $spanData */
        $spanData = $spans[0];
        $events = $spanData->getEvents();
        self::assertCount(1, $events);

        /** @var \OpenTelemetry\SDK\Trace\Event $event */
        $event = $events[0];
        self::assertSame('user-42', $event->getAttributes()->get('runit.occurrence.subject'));
        self::assertSame('act-7', $event->getAttributes()->get('runit.activity.id'));
        self::assertSame('on', $event->getAttributes()->get('tenantflag'));
    }

    public function testRedactedOccurrenceUsesFallbacksForMissingFields(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false
        );
        $mapper->mapRedactedOccurrence([]);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('redacted', $attributes['runit.occurrence.id']);
        self::assertSame('runit.artifact.redacted', $attributes['runit.occurrence.type']);
        self::assertSame('runit://redacted', $attributes['runit.occurrence.source']);
        self::assertSame('', $attributes['runit.execution.id']);
        self::assertTrue($attributes['runit.redacted']);
    }

    public function testRedactedOccurrenceExportsProvidedFieldsOnly(): void
    {
        $normalized = [
            'id' => 'occ-red',
            'type' => 'payment.captured.v1',
            'subject' => 'user-42',
            'activity_id' => 'act-9',
            'extension_attributes' => ['tenantflag' => 'on'],
            'time' => 1_600,
        ];

        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false
        );
        $mapper->mapRedactedOccurrence($normalized);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('occ-red', $attributes['runit.occurrence.id']);
        self::assertSame('payment.captured.v1', $attributes['runit.occurrence.type']);
        self::assertSame('user-42', $attributes['runit.occurrence.subject']);
        self::assertSame('act-9', $attributes['runit.activity.id']);
        self::assertSame('on', $attributes['tenantflag']);
    }

    public function testRedactedSnapshotFallsBackWhenFieldsAreMissing(): void
    {
        $mapper = new DefaultOccurrenceLogMapper(
            $this->kit->loggerProvider,
            SnapshotPayloadPolicy::HashOnly,
            true,
            false
        );
        $mapper->mapRedactedSnapshot(['classification' => 'terminal', 'content_hash' => 'sha-256:abc']);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('redacted', $attributes['runit.snapshot.id']);
        self::assertSame('runit.artifact.redacted', $attributes['runit.snapshot.type']);
        self::assertSame('terminal', $attributes['runit.snapshot.classification']);
        self::assertSame('sha-256:abc', $attributes['runit.snapshot.hash']);
        self::assertTrue($attributes['runit.redacted']);
    }

    public function testSnapshotActivityAndAttributesAreExported(): void
    {
        $snapshot = RunItArtifactFactory::snapshot('snap-act', 'exec-1', ['key' => 'value']);

        $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::None, true, false);
        $mapper->mapSnapshot($snapshot);

        $attributes = $this->kit->log(0)->getAttributes()->toArray();
        self::assertSame('snap-act', $attributes['runit.snapshot.id']);
        self::assertArrayHasKey('runit.snapshot.classification', $attributes);
    }
}
