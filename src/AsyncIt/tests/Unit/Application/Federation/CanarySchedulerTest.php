<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Application\Federation\CanaryScheduler;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\CanaryScheduler
 */
final class CanarySchedulerTest extends TestCase
{
    public function testDispatchRoutesToBaselineWhenWeightIsZero(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.0, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 1);
        $task = new FakeTask('t1');

        $result = $scheduler->dispatch($task, fn (): string => 'result');

        self::assertSame('result', $result);
        self::assertSame(['baseline.dispatch'], $baseline->calls());
        self::assertSame([], $canary->calls());
    }

    public function testDispatchRoutesToCanaryWhenWeightIsOne(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 1.0, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 1);
        $task = new FakeTask('t1');

        $result = $scheduler->dispatch($task, fn (): string => 'result');

        self::assertSame('result', $result);
        self::assertSame([], $baseline->calls());
        self::assertSame(['canary.dispatch'], $canary->calls());
    }

    public function testDispatchPreservesOriginalException(): void
    {
        $baseline = new FakeScheduler('baseline', shouldThrow: true);
        $canary = new FakeScheduler('canary');

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.0, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 1);
        $task = new FakeTask('t1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $scheduler->dispatch($task, fn (): string => 'never');
    }

    public function testRollsBackOnRelativeErrorRate(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary', shouldThrow: true);

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.0, latencyFactor: 1.5, minCalls: 5);
        $task = new FakeTask('t1');

        // Make enough calls that both paths get >= minCalls calls.
        // Canary always throws (error rate 1.0), baseline always succeeds (error rate 0.0).
        // Canary error rate (1.0) > baseline error rate (0.0) + 0.0 → rollback.
        for ($i = 0; $i < 100; $i++) {
            try {
                $scheduler->dispatch($task, fn (): string => 'ok');
            } catch (RuntimeException) {
                // Expected from canary path.
            }
        }

        self::assertTrue($scheduler->isRolledBack());

        // After rollback, all dispatches go to baseline.
        $result = $scheduler->dispatch($task, fn (): string => 'baseline-result');
        self::assertSame('baseline-result', $result);
    }

    public function testDoesNotRollBackWhenBelowMargin(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary', shouldThrow: true);

        // errorMargin: 0.5 means canary must exceed baseline by 50 percentage points.
        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.5, latencyFactor: 1.5, minCalls: 10);
        $task = new FakeTask('t1');

        // Fewer than minCalls on each path → no rollback evaluation.
        for ($i = 0; $i < 5; $i++) {
            try {
                $scheduler->dispatch($task, fn (): string => 'never');
            } catch (RuntimeException) {
                // Expected.
            }
        }

        self::assertFalse($scheduler->isRolledBack());
    }

    public function testResetRollbackReEnablesCanary(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary', shouldThrow: true);

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.0, latencyFactor: 1.5, minCalls: 1);
        $task = new FakeTask('t1');

        // Trigger rollback: canary errors exceed baseline errors.
        for ($i = 0; $i < 100; $i++) {
            try {
                $scheduler->dispatch($task, fn (): string => 'ok');
            } catch (RuntimeException) {
                // Expected from canary path.
            }
        }

        self::assertTrue($scheduler->isRolledBack());

        $scheduler->resetRollback();
        self::assertFalse($scheduler->isRolledBack());

        // After reset, dispatch can route to either path; retry until baseline succeeds.
        $result = null;
        for ($i = 0; $i < 20; $i++) {
            try {
                $result = $scheduler->dispatch($task, fn (): string => 'baseline-result');
                break;
            } catch (RuntimeException) {
                // Canary path threw; retry.
            }
        }

        self::assertNotNull($result);
        self::assertSame('baseline-result', $result);
    }

    public function testNonDispatchMethodsDelegateToBaseline(): void
    {
        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        $scheduler = new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 10);
        $task = new FakeTask('t1');

        $scheduler->submit($task, function (): void {
        });
        $scheduler->run();
        self::assertSame('result', $scheduler->result($task));
        self::assertNull($scheduler->currentTask());

        $scheduler->awaitDelay(new FakeDuration(1000));

        self::assertFalse($scheduler->isRolledBack());
        self::assertSame(['baseline.submit', 'baseline.run', 'baseline.result', 'baseline.currentTask', 'baseline.awaitDelay'], $baseline->calls());
        self::assertSame(0.5, $scheduler->canaryWeight());
        self::assertSame(['baseline' => ['calls' => 0, 'errors' => 0, 'totalLatencyNanos' => 0], 'canary' => ['calls' => 0, 'errors' => 0, 'totalLatencyNanos' => 0]], $scheduler->metrics());
    }

    public function testRejectsInvalidWeight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        new CanaryScheduler($baseline, $canary, canaryWeight: 1.5, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 10);
    }

    public function testRejectsInvalidErrorMargin(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: -0.1, latencyFactor: 1.5, minCalls: 10);
    }

    public function testRejectsErrorMarginAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 1.1, latencyFactor: 1.5, minCalls: 10);
    }

    public function testRejectsInvalidLatencyFactor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.1, latencyFactor: 0.5, minCalls: 10);
    }

    public function testRejectsInvalidMinCalls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $baseline = new FakeScheduler('baseline');
        $canary = new FakeScheduler('canary');

        new CanaryScheduler($baseline, $canary, canaryWeight: 0.5, errorMargin: 0.1, latencyFactor: 1.5, minCalls: 0);
    }

    public function testFakeDurationEquals(): void
    {
        $a = new FakeDuration(1000);
        $b = new FakeDuration(1000);
        $c = new FakeDuration(2000);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}

final class FakeScheduler implements EngineSchedulerInterface
{
    /** @var list<string> */
    private array $calls = [];

    public function __construct(
        private readonly string $label,
        private readonly bool $shouldThrow = false,
    ) {
    }

    /** @return list<string> */
    public function calls(): array
    {
        return $this->calls;
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $this->calls[] = $this->label . '.submit';
    }

    public function run(): void
    {
        $this->calls[] = $this->label . '.run';
    }

    public function result(TaskInterface $task): mixed
    {
        $this->calls[] = $this->label . '.result';

        return 'result';
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        $this->calls[] = $this->label . '.dispatch';

        if ($this->shouldThrow) {
            throw new RuntimeException('boom');
        }

        return $operation();
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        $this->calls[] = $this->label . '.spawn';

        return new FakeTask('spawned');
    }

    public function await(TaskInterface $task): mixed
    {
        $this->calls[] = $this->label . '.await';

        return 'awaited';
    }

    public function currentTask(): ?TaskInterface
    {
        $this->calls[] = $this->label . '.currentTask';

        return null;
    }

    public function awaitDelay(DurationInterface $duration): void
    {
        $this->calls[] = $this->label . '.awaitDelay';
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        $this->calls[] = $this->label . '.retry';

        return $operation();
    }

    public function health(): SchedulerHealthInterface
    {
        $this->calls[] = $this->label . '.health';

        return new FakeHealth();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->calls[] = $this->label . '.swapCapabilities';
    }
}

final class FakeHealth implements SchedulerHealthInterface
{
    public function isHealthy(): bool
    {
        return true;
    }

    public function pendingTaskCount(): int
    {
        return 0;
    }

    public function activeHandleCount(): int
    {
        return 0;
    }

    public function uptime(): DurationInterface
    {
        return new FakeDuration(0);
    }

    /** @return list<Throwable> */
    public function recentErrors(int $n): array
    {
        return [];
    }
}

final readonly class FakeDuration implements DurationInterface
{
    public function __construct(private int $nanos)
    {
    }

    public function nanoseconds(): int
    {
        return $this->nanos;
    }

    public function seconds(): int
    {
        return (int) floor($this->nanos / 1_000_000_000);
    }

    public function toString(): string
    {
        return $this->nanos . 'ns';
    }

    public function equals(DurationInterface $other): bool
    {
        return $other->nanoseconds() === $this->nanos;
    }
}

final readonly class FakeTask implements TaskInterface
{
    public function __construct(private string $id)
    {
    }

    public function id(): TaskIdInterface
    {
        return new TaskId($this->id);
    }

    public function executionId(): ExecutionIdInterface
    {
        return new class () implements ExecutionIdInterface {
            public function __toString(): string
            {
                return 'e1';
            }

            public function toString(): string
            {
                return 'e1';
            }

            public function equals(StringIdentifierInterface $other): bool
            {
                return $other->toString() === 'e1';
            }
        };
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new class () implements CancellationScopeInterface {
            public function isCancelled(): bool
            {
                return false;
            }

            public function cancel(?CancellationReason $reason = null): void
            {
            }

            public function reason(): ?CancellationReason
            {
                return null;
            }

            public function throwIfCancelled(): void
            {
            }

            public function onCancel(callable $callback): void
            {
            }
        };
    }

    public function status(): TaskStatus
    {
        return TaskStatus::Created;
    }

    public function transitionTo(TaskStatus $next): void
    {
    }

    public function complete(TaskStatus $outcome): void
    {
    }
}
