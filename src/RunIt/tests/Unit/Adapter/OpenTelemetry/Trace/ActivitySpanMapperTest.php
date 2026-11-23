<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Trace;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultActivitySpanMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.3 — Activity to span mapping.
 */
#[CoversClass(DefaultActivitySpanMapper::class)]
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
     * @return SpanDataInterface[]
     */
    private function spans(): array
    {
        /** @var SpanDataInterface[] $spans */
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
        self::assertSame('act-1', $span->getAttributes()->get(AttributeKeys::ACTIVITY_ID));
        self::assertSame('exec-1', $span->getAttributes()->get(AttributeKeys::EXECUTION_ID));
        self::assertSame('succeeded', $span->getAttributes()->get(AttributeKeys::ACTIVITY_STATUS));
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
        self::assertSame('act-parent', $spans[2]->getAttributes()->get(AttributeKeys::ACTIVITY_PARENT_ID));
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
        $mapper = new DefaultActivitySpanMapper(
            tracerProvider: null,
            store: $this->store,
            statusMap: new SpanStatusMap(),
            kindMap: new SpanKindMap(),
        );
        $activity = RunItArtifactFactory::activity('act-none', 'exec-1', 'parse', 'internal', 'succeeded');

        $mapper->map($activity);
        $mapper->mapRedactedActivity(['id' => 'redacted']);

        self::assertCount(0, $this->spans());
        self::assertNull($this->store->activityContext('redacted'));
    }

    public function testRedactedActivityUsesParentAndOnlyExportsScalarAttributes(): void
    {
        $this->mapExecution('exec-1');
        $this->mapper->mapRedactedActivity([
            'id' => 'parent', 'execution_id' => 'exec-1', 'name' => 'parent',
            'status' => 'succeeded', 'started_at' => 100, 'ended_at' => 101,
        ]);
        $this->mapper->mapRedactedActivity([
            'id' => 'child', 'execution_id' => 'exec-1', 'parent_activity_id' => 'parent',
            'name' => 'child', 'kind' => 'internal', 'status' => 'failed',
            'started_at' => 102, 'ended_at' => 103,
            'attributes' => ['safe' => 'visible', 'count' => 2, 'nested' => ['secret' => 'hidden'], '' => 'hidden'],
            'throwable' => ['class' => 'SafeError', 'message' => '[redacted]'],
        ]);

        $spans = $this->spans();
        self::assertSame($spans[0]->getContext()->getSpanId(), $spans[1]->getParentSpanId());
        self::assertSame($spans[1]->getContext()->getSpanId(), $spans[2]->getParentSpanId());
        self::assertSame('parent', $spans[2]->getAttributes()->get(AttributeKeys::ACTIVITY_PARENT_ID));
        self::assertSame('visible', $spans[2]->getAttributes()->get('safe'));
        self::assertSame(2, $spans[2]->getAttributes()->get('count'));
        self::assertNull($spans[2]->getAttributes()->get('nested'));
        self::assertSame(StatusCode::STATUS_ERROR, $spans[2]->getStatus()->getCode());
        self::assertSame('[redacted]', $spans[2]->getEvents()[0]->getAttributes()->get('exception.message'));
        self::assertSame($spans[2]->getContext()->getSpanId(), $this->store->activityContext('child')?->getSpanId());
    }

    public function testRedactedActivityWithoutIdentityUsesFallbacks(): void
    {
        $this->mapper->mapRedactedActivity(['id' => 42, 'name' => '', 'status' => null]);

        $span = $this->span(0);
        self::assertNotSame('', $span->getName());
        self::assertSame('', $span->getAttributes()->get(AttributeKeys::ACTIVITY_ID));
        self::assertCount(0, $span->getEvents());
        self::assertNull($this->store->activityContext('42'));
    }
}
