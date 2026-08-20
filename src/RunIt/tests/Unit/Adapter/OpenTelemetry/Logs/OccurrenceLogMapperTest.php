<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Logs;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
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
            $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::HashOnly, true, true);
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
        $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::HashOnly, false, false);
        $mapper->mapOccurrence(RunItArtifactFactory::occurrence('occ-2', 'exec-1', 'execution.started.v1'));

        self::assertCount(0, $this->kit->logs());
    }

    public function testSnapshotExportsHashButNotPayloadByDefault(): void
    {
        $mapper = new DefaultOccurrenceLogMapper($this->kit->loggerProvider, SnapshotPayloadPolicy::HashOnly, true, false);
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
}
