<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\BackpressureGate;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\BackpressureGate
 */
final class BackpressureGateTest extends TestCase
{
    public function testErrorPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testDropPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Drop, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testBlockPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Block, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testBelowCapacityAlwaysProceeds(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 2);
        $task1 = new BackpressureFakeTask('t1');
        $task2 = new BackpressureFakeTask('t2');

        self::assertSame('first', $gate->process($task1, fn (): string => 'first'));
        self::assertSame('second', $gate->process($task2, fn (): string => 'second'));
    }

    public function testSlotReleasedAfterDispatch(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 1);
        $task1 = new BackpressureFakeTask('t1');
        $task2 = new BackpressureFakeTask('t2');

        self::assertSame('first', $gate->process($task1, fn (): string => 'first'));
        self::assertSame('second', $gate->process($task2, fn (): string => 'second'));
    }
}

final readonly class BackpressureFakeTask implements TaskInterface
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
        return new BackpressureFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new BackpressureFakeCancellationScope();
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

final class BackpressureFakeExecutionId implements ExecutionIdInterface
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

final class BackpressureFakeCancellationScope implements CancellationScopeInterface
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
