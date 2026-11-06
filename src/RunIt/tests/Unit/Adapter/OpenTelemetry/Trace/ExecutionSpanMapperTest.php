<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Trace;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.2 — Execution to trace mapping.
 */
final class ExecutionSpanMapperTest extends Unit
{
    private InMemoryExporter $spanExporter;

    private DefaultExecutionSpanMapper $mapper;

    private InMemoryExecutionSpanStore $store;

    protected function _before(): void
    {
        $this->spanExporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->spanExporter));
        $this->store = new InMemoryExecutionSpanStore();
        $this->mapper = new DefaultExecutionSpanMapper(
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

    public function testMapsRootExecutionToRootSpanWithAttributesAndStatus(): void
    {
        $execution = RunItArtifactFactory::execution('exec-1', 'import', 'queue.consumer.process', 'succeeded');

        $this->mapper->map($execution);

        $spans = $this->spans();
        self::assertCount(1, $spans);
        $span = $spans[0];
        self::assertSame('import', $span->getName());
        self::assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
        self::assertSame('exec-1', $span->getAttributes()->get('runit.execution.id'));
        self::assertSame('import', $span->getAttributes()->get('runit.execution.name'));
        self::assertSame('queue.consumer.process', $span->getAttributes()->get('runit.execution.kind'));
        self::assertSame('succeeded', $span->getAttributes()->get('runit.execution.status'));
        self::assertSame(1_000_000_000_000, $span->getStartEpochNanos());
        self::assertSame(2_000_000_000_000, $span->getEndEpochNanos());
    }

    public function testMapsSpanKindFromExecutionKind(): void
    {
        $execution = RunItArtifactFactory::execution('exec-2', 'http-in', 'http.server.request', 'succeeded');

        $this->mapper->map($execution);

        self::assertSame(2, $this->span(0)->getKind());
    }

    public function testRecordsExceptionEventForFailedExecution(): void
    {
        $execution = RunItArtifactFactory::execution('exec-3', 'import', 'queue.consumer.process', 'failed');

        $this->mapper->map($execution);

        $span = $this->span(0);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertSame('failed', $span->getStatus()->getDescription());
        $events = $span->getEvents();
        self::assertCount(1, $events);
        self::assertSame('exception', $events[0]->getName());
        self::assertSame('RuntimeException', $events[0]->getAttributes()->get('exception.type'));
        self::assertSame('execution failed', $events[0]->getAttributes()->get('exception.message'));
    }

    public function testParentExecutionBecomesParentSpan(): void
    {
        $parent = RunItArtifactFactory::execution('exec-parent', 'parent', 'internal', 'succeeded');
        $child = RunItArtifactFactory::execution('exec-child', 'child', 'internal', 'succeeded', 'exec-parent');

        $this->mapper->map($parent);
        $this->mapper->map($child);

        $spans = $this->spans();
        self::assertCount(2, $spans);
        $childSpan = $spans[1];
        self::assertSame($spans[0]->getContext()->getSpanId(), $childSpan->getParentSpanId());
        self::assertSame('exec-parent', $childSpan->getAttributes()->get('runit.execution.parent_id'));
    }

    public function testStoresSpanContextForParenting(): void
    {
        $execution = RunItArtifactFactory::execution('exec-store', 'import', 'internal', 'succeeded');

        $this->mapper->map($execution);

        self::assertNotNull($this->store->executionContext('exec-store'));
    }

    public function testMissingTracerProviderExportsNothing(): void
    {
        $mapper = new DefaultExecutionSpanMapper(tracerProvider: null, store: $this->store, statusMap: new SpanStatusMap(), kindMap: new SpanKindMap());
        $execution = RunItArtifactFactory::execution('exec-none', 'import', 'internal', 'succeeded');

        $mapper->map($execution);

        self::assertCount(0, $this->spans());
    }
}
