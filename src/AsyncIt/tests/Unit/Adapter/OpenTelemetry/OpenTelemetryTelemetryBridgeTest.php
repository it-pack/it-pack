<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge
 */
final class OpenTelemetryTelemetryBridgeTest extends TestCase
{
    public function testIsInitializedFalseBeforeFirstRecord(): void
    {
        $bridge = new OpenTelemetryTelemetryBridge();

        self::assertFalse($bridge->isInitialized());
    }

    public function testIsInitializedTrueAfterRecord(): void
    {
        $bridge = new OpenTelemetryTelemetryBridge();
        $bridge->record('task.spawned', ['taskId' => 't-1']);

        self::assertTrue($bridge->isInitialized());
    }

    public function testRecordWithNullTracerProviderIsNoop(): void
    {
        $bridge = new OpenTelemetryTelemetryBridge();

        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record('task.completed', ['taskId' => 't-1', 'status' => 'succeeded']);

        self::assertTrue($bridge->isInitialized());
    }

    public function testRecordWithUnknownEventIsNoop(): void
    {
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->expects($this->never())->method('spanBuilder');

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.unknown', ['taskId' => 't-1']);

        self::assertTrue($bridge->isInitialized());
    }

    public function testEventsAlwaysEmpty(): void
    {
        $bridge = new OpenTelemetryTelemetryBridge();
        $bridge->record('task.spawned', ['taskId' => 't-1']);

        self::assertSame([], $bridge->events());
    }

    public function testTaskSpawnedCreatesSpanWithAttributes(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->never())->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->expects($this->once())->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record(
            'task.spawned',
            [
                'taskId' => 't-1',
                'status' => 'running',
                'taskKind' => 'io',
                'executionId' => 'exec-1',
            ],
        );

        self::assertTrue($bridge->isInitialized());
    }

    public function testTaskSuspendedAddsSpanEvent(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('addEvent')
            ->with('itpack.asyncit.task.suspended', ['awaitPoint' => 'delay'])
        ;

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record('task.suspended', ['taskId' => 't-1', 'awaitPoint' => 'delay']);
    }

    public function testTaskResumedAddsSpanEvent(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('addEvent')
            ->with('itpack.asyncit.task.resumed', ['awaitPoint' => 'readable'])
        ;

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record('task.resumed', ['taskId' => 't-1', 'awaitPoint' => 'readable']);
    }

    public function testTaskCompletedEndsSpanAndRemovesIt(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record('task.completed', ['taskId' => 't-1', 'status' => 'succeeded']);
    }

    public function testTaskCompletedFailedRecordsExceptionAndErrorStatus(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('recordException')->with(self::isInstanceOf(RuntimeException::class));
        $span->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_ERROR, 'failed');
        $span->expects($this->once())->method('end');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record(
            'task.completed',
            [
                'taskId' => 't-1',
                'status' => TaskStatus::Failed->value,
                'throwable' => new RuntimeException('boom'),
            ],
        );
    }

    public function testTaskCompletedWithDurationEndsSpanWithMilliseconds(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('end')->with(500);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->method('setAttribute')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.spawned', ['taskId' => 't-1']);
        $bridge->record(
            'task.completed',
            [
                'taskId' => 't-1',
                'status' => 'succeeded',
                'duration' => Duration::ofNanoseconds(500_000_000),
            ],
        );
    }

    public function testTaskCompletedWithoutSpanIsSafeNoop(): void
    {
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->expects($this->never())->method('spanBuilder');

        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $bridge = new OpenTelemetryTelemetryBridge($provider);
        $bridge->record('task.completed', ['taskId' => 'unknown', 'status' => 'succeeded']);

        self::assertTrue($bridge->isInitialized());
    }
}
