<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\MiddlewareScheduler;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\MiddlewareScheduler
 */
final class MiddlewareSchedulerTest extends TestCase
{
    public function testDispatchRunsMiddlewaresInOrderThenInner(): void
    {
        $log = new OrderLog();
        $inner = new FakeEngineScheduler();
        $mw1 = new OrderedMiddleware($log, 'mw1');
        $mw2 = new OrderedMiddleware($log, 'mw2');

        $scheduler = new MiddlewareScheduler($inner, [$mw1, $mw2]);
        $task = new MiddlewareFakeTask('t1');

        $result = $scheduler->dispatch($task, fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertSame(
            ['mw1-before', 'mw2-before', 'mw2-after', 'mw1-after'],
            $log->entries,
        );
    }

    public function testMiddlewareCanShortCircuitWithoutCallingNext(): void
    {
        $inner = new FakeEngineScheduler();
        $shortCircuit = new ShortCircuitMiddleware();
        $scheduler = new MiddlewareScheduler($inner, [$shortCircuit]);
        $task = new MiddlewareFakeTask('t1');

        self::assertSame('short-circuited', $scheduler->dispatch($task, fn (): string => 'never-called'));
    }

    public function testNonDispatchMethodsDelegateToInner(): void
    {
        $inner = new FakeEngineScheduler();
        $scheduler = new MiddlewareScheduler($inner);
        $task = new MiddlewareFakeTask('t1');

        self::assertSame('inner-result', $scheduler->result($task));

        $health = $scheduler->health();
        self::assertTrue($health->isHealthy());
        self::assertSame(0, $health->pendingTaskCount());
        self::assertSame(0, $health->activeHandleCount());
        self::assertSame(0, $health->uptime()->nanoseconds());
        self::assertSame([], $health->recentErrors(10));
    }
}

final class OrderLog
{
    /** @var array<string> */
    public array $entries = [];
}

final class FakeEngineScheduler implements EngineSchedulerInterface
{
    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        return $operation();
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
    }

    public function run(): void
    {
    }

    public function result(TaskInterface $task): mixed
    {
        return 'inner-result';
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        return new MiddlewareFakeTask('spawned');
    }

    public function await(TaskInterface $task): mixed
    {
        return null;
    }

    public function currentTask(): ?TaskInterface
    {
        return null;
    }

    public function awaitDelay(DurationInterface $duration): void
    {
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        return $operation();
    }

    public function health(): SchedulerHealthInterface
    {
        return new MiddlewareFakeSchedulerHealth();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
    }
}

final class MiddlewareFakeSchedulerHealth implements SchedulerHealthInterface
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
        return new MiddlewareFakeDuration();
    }

    public function recentErrors(int $n): array
    {
        return [];
    }
}

final class MiddlewareFakeDuration implements DurationInterface
{
    public function nanoseconds(): int
    {
        return 0;
    }

    public function seconds(): int
    {
        return 0;
    }

    public function toString(): string
    {
        return '0s';
    }
}

final readonly class MiddlewareFakeTask implements TaskInterface
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
        return new MiddlewareFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new MiddlewareFakeCancellationScope();
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

final class MiddlewareFakeExecutionId implements ExecutionIdInterface
{
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
        return $other instanceof self && $other->toString() === $this->toString();
    }
}

final class MiddlewareFakeCancellationScope implements CancellationScopeInterface
{
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
}

final readonly class OrderedMiddleware implements SchedulerMiddlewareInterface
{
    public function __construct(
        private OrderLog $log,
        private string $name,
    ) {
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        $this->log->entries[] = $this->name . '-before';
        $result = $next();
        $this->log->entries[] = $this->name . '-after';

        return $result;
    }
}

final class ShortCircuitMiddleware implements SchedulerMiddlewareInterface
{
    public function process(TaskInterface $task, callable $next): mixed
    {
        return 'short-circuited';
    }
}
