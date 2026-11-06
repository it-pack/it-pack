<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Trace;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultActivitySpanMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.3 — Activity to span mapping.
 */
final class ActivitySpanMapperTest extends Unit
{
    private InMemoryExporter $spanExporter;

    private DefaultActivitySpanMapper $mapper;

    private DefaultExecutionSpanMapper $executionMapper;

    private InMemoryExecutionSpanStore $store;

    protected function _before(): void
    {
        $this->spanExporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->spanExporter));
        $this->store = new InMemoryExecutionSpanStore();
        $this->mapper = new DefaultActivitySpanMapper(
            $tracerProvider,
            $this->store,
            new SpanStatusMap(),
            new SpanKindMap()
        );
        $this->executionMapper = new DefaultExecutionSpanMapper(
            $tracerProvider,
            $this->store,
            new SpanStatusMap(),
            new SpanKindMap()
        );
    }

    /**
     * @return SpanDataInterfacee[]
     */
    private function spans(): array
    {
        /** @var SpanDataInterfacee[] $spans */
        $spans = $this->spanExporter->getSpans();

        return $spans;
    }

    private function span(int $index): SpanDataInterface
    {
        return $this->spans()[$index];
    }

    private function mapExecution(string $id): void
    {
        $this->executionMapper->map(RunItArtifactFactory::execution($id, 'exec', 'internal', 'succeeded'));
    }

    public function testMapsActivityWithIdentityAndStatus(): void
    {
        $activity = RunItArtifactFactory::activity('act-1', 'exec-1', 'parse', 'internal', 'succeeded');

        $this->mapper->map($activity);

        $span = $this->span(0);
        self::assertSame('parse', $span->getName());
        self::assertSame('act-1', $span->getAttributes()->get('runit.activity.id'));
        self::assertSame('exec-1', $span->getAttributes()->get('runit.execution.id'));
        self::assertSame('succeeded', $span->getAttributes()->get('runit.activity.status'));
        self::assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
    }

    public function testActivityParentedToExecutionSpan(): void
    {
        $this->mapExecution('exec-1');
        $activity = RunItArtifactFactory::activity('act-1', 'exec-1', 'parse', 'internal', 'succeeded');

        $this->mapper->map($activity);

        $spans = $this->spans();
        self::assertSame($spans[0]->getContext()->getSpanId(), $spans[1]->getParentSpanId());
    }

    public function testNestedActivitiesParentCorrectly(): void
    {
        $this->mapExecution('exec-1');
        $parent = RunItArtifactFactory::activity('act-parent', 'exec-1', 'outer', 'internal', 'succeeded');
        $child = RunItArtifactFactory::activity('act-child', 'exec-1', 'inner', 'internal', 'succeeded', 'act-parent');

        $this->mapper->map($parent);
        $this->mapper->map($child);

        $spans = $this->spans();
        self::assertSame($spans[1]->getContext()->getSpanId(), $spans[2]->getParentSpanId());
        self::assertSame('act-parent', $spans[2]->getAttributes()->get('runit.activity.parent_id'));
    }

    public function testFailedActivityRecordsException(): void
    {
        $activity = RunItArtifactFactory::activity('act-fail', 'exec-1', 'parse', 'internal', 'failed');

        $this->mapper->map($activity);

        $span = $this->span(0);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $events = $span->getEvents();
        self::assertCount(1, $events);
        self::assertSame('exception', $events[0]->getName());
        self::assertSame('RuntimeException', $events[0]->getAttributes()->get('exception.type'));
    }

    public function testCancelledAndTimeoutAreDistinctErrorStatuses(): void
    {
        $cancelled = RunItArtifactFactory::activity('act-cancel', 'exec-1', 'parse', 'internal', 'cancelled');
        $timedOut = RunItArtifactFactory::activity('act-timeout', 'exec-1', 'parse', 'internal', 'timed_out');

        $this->mapper->map($cancelled);
        $this->mapper->map($timedOut);

        $spans = $this->spans();
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('cancelled', $spans[0]->getStatus()->getDescription());
        self::assertSame('timed_out', $spans[1]->getStatus()->getDescription());
    }

    public function testMissingTracerProviderExportsNothing(): void
    {
        $mapper = new DefaultActivitySpanMapper(tracerProvider: null, store: $this->store, statusMap: new SpanStatusMap(), kindMap: new SpanKindMap());
        $activity = RunItArtifactFactory::activity('act-none', 'exec-1', 'parse', 'internal', 'succeeded');

        $mapper->map($activity);

        self::assertCount(0, $this->spans());
    }
}
