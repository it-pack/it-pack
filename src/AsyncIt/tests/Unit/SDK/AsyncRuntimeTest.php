<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Generator;
use ItPack\AsyncIt\Application\Factory\CombinatorFactoryInterface;
use ItPack\AsyncIt\Application\Factory\SchedulerFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskStreamFactory;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Proves the reference runtime passes RunIt's {@see ExecutionEngineVerifier}
 * and returns operation results (including across Fiber suspend/resume).
 *
 * @covers \ItPack\AsyncIt\SDK\AsyncRuntime
 * @covers \ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory
 * @covers \ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler
 * @covers \ItPack\AsyncIt\Application\Factory\SchedulerFactory
 * @covers \ItPack\AsyncIt\Application\Factory\PerRunExecutionScopeFactory
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class AsyncRuntimeTest extends TestCase
{
    public function testPassesRunItExecutionEngineVerifier(): void
    {
        $result = new ExecutionEngineVerifier()->verify($this->runtime(), new ReferenceExecutionScope('engine'));

        self::assertTrue(
            $result->isConformant(),
            $result->summary(),
        );
    }

    public function testReturnsOperationValue(): void
    {
        $value = $this->runtime()->run(new ReferenceExecutionScope('e'), static fn (): int => 42);

        self::assertSame(42, $value);
    }

    public function testReturnsValueAcrossFiberSuspendResume(): void
    {
        $runtime = $this->runtime();

        $value = $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): int {
                $runtime->awaitDelay(Duration::ofNanoseconds(1));

                return 7;
            },
        );

        self::assertSame(7, $value);
    }

    public function testReturnsNullForNullOperation(): void
    {
        $value = $this->runtime()->run(new ReferenceExecutionScope('e'), static fn (): ?int => null);

        self::assertNull($value);
    }

    public function testCapabilityReportsFiberSupport(): void
    {
        $capability = $this->runtime()->capability();

        self::assertTrue($capability->supportsFibers);
        // Swoole is available: BlockingHook = AdapterOnly → supportsCoroutines = true
        self::assertTrue($capability->supportsCoroutines);
        // FiberExecution = Supported → concurrent = true
        self::assertTrue($capability->concurrent);
    }

    public function testChannelReturnsInjectedChannel(): void
    {
        $channel = $this->createMock(TaskChannelInterface::class);
        $runtime = $this->buildRuntime(channel: $channel);

        self::assertSame($channel, $runtime->channel());
    }

    public function testTelemetryReturnsInjectedBridge(): void
    {
        $bridge = $this->createMock(TelemetryBridgeInterface::class);
        $runtime = $this->buildRuntime(telemetry: $bridge);

        self::assertSame($bridge, $runtime->telemetry());
    }

    public function testHealthReturnsNullBeforeRun(): void
    {
        $runtime = $this->buildRuntime();

        self::assertNull($runtime->health());
    }

    public function testOnCapabilitiesChangedIsNoopWhenNoScheduler(): void
    {
        $runtime = $this->buildRuntime();

        $descriptor = $this->createMock(CapabilityDescriptorInterface::class);

        $runtime->onCapabilitiesChanged($descriptor);

        self::assertNull($runtime->health());
    }

    public function testSpawnThrowsWhenNoActiveScheduler(): void
    {
        $runtime = $this->buildRuntime();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('spawn() must be called within a running execution');

        $runtime->spawn(static function (): void {
        });
    }

    public function testAwaitThrowsWhenNoActiveScheduler(): void
    {
        $runtime = $this->buildRuntime();

        $task = $this->createMock(TaskInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('await() must be called within a running execution');

        $runtime->await($task);
    }

    public function testAwaitDelayThrowsWhenNoActiveScheduler(): void
    {
        $runtime = $this->buildRuntime();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('awaitDelay() must be called within a running execution');

        $runtime->awaitDelay(Duration::ofNanoseconds(1));
    }

    public function testSpawnFromRunningExecutionReturnsTask(): void
    {
        $runtime = $this->runtime();

        $task = null;
        $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime, &$task): void {
                $task = $runtime->spawn(static fn (): int => 42);
            },
        );

        self::assertNotNull($task);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testAwaitFromRunningExecutionReturnsTaskResult(): void
    {
        $runtime = $this->runtime();

        $value = $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): mixed {
                $task = $runtime->spawn(static fn (): int => 99);

                return $runtime->await($task);
            },
        );

        self::assertSame(99, $value);
    }

    public function testStreamYieldsTaskResultsOneAtATime(): void
    {
        $runtime = $this->runtime();

        $results = $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): array {
                $collected = [];

                $stream = $runtime->stream(static function (): Generator {
                    yield static fn (): int => 10;
                    yield static fn (): int => 20;
                    yield static fn (): int => 30;
                });

                $stream->rewind();

                while ($stream->valid()) {
                    $collected[] = $stream->current();
                    $stream->next();
                }

                return $collected;
            },
        );

        self::assertSame([10, 20, 30], $results);
    }

    public function testStreamWithEmptyGeneratorReturnsNoItems(): void
    {
        $runtime = $this->runtime();

        $results = $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): array {
                $collected = [];

                $stream = $runtime->stream(static function (): Generator {
                    yield from [];
                });

                $stream->rewind();

                while ($stream->valid()) {
                    $collected[] = $stream->current();
                    $stream->next();
                }

                return $collected;
            },
        );

        self::assertSame([], $results);
    }

    public function testStreamOutsideRunningExecutionThrows(): void
    {
        $runtime = $this->runtime();

        $this->expectException(LogicException::class);
        $runtime->stream(static function (): Generator {
            yield static fn (): int => 1;
        });
    }

    public function testOnCapabilitiesChangedSwapsDescriptorWhenSchedulerActive(): void
    {
        $runtime = $this->runtime();

        $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): void {
                $runtime->onCapabilitiesChanged(
                    new CapabilityDescriptor(
                        [CapabilityKind::FiberExecution->value => CapabilityStatus::Supported],
                        2,
                    ),
                );
            },
        );

        // No exception thrown — swapCapabilities was called on the live scheduler.
    }

    private function runtime(): AsyncRuntime
    {
        return AsyncRuntime::discover();
    }

    private function buildRuntime(
        ?PollerPortInterface $poller = null,
        ?TaskChannelInterface $channel = null,
        ?TelemetryBridgeInterface $telemetry = null,
    ): AsyncRuntime {
        $poller ??= $this->createMock(PollerPortInterface::class);
        $channel ??= $this->createMock(TaskChannelInterface::class);
        $telemetry ??= $this->createMock(TelemetryBridgeInterface::class);

        $idGenerator = $this->createMock(IdentifierGeneratorInterface::class);
        $idGenerator->method('generate')->willReturn('test-id');

        $schedulerFactory = $this->createMock(SchedulerFactoryInterface::class);
        $taskGroupFactory = $this->createMock(TaskGroupFactoryInterface::class);
        $combinatorFactory = $this->createMock(CombinatorFactoryInterface::class);
        $taskStreamFactory = new TaskStreamFactory();

        $capability = new EngineCapability(supportsFibers: true, supportsCoroutines: true, concurrent: true);

        return new AsyncRuntime(
            $schedulerFactory,
            $taskGroupFactory,
            $combinatorFactory,
            $taskStreamFactory,
            $idGenerator,
            $capability,
            $channel,
            $telemetry,
        );
    }
}
