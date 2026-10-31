<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Application\Federation\ResourceQuotaChecker;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\ResourceQuotaChecker
 */
final class ResourceQuotaCheckerTest extends TestCase
{
    public function testAllowsDispatchBelowQuota(): void
    {
        $checker = new ResourceQuotaChecker(2);
        $task = new ResourceQuotaFakeTask('t1');

        $result = $checker->process($task, fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testRejectsWhenQuotaIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResourceQuotaChecker(0);
    }

    public function testSlotReleasedAfterDispatch(): void
    {
        $checker = new ResourceQuotaChecker(1);
        $task1 = new ResourceQuotaFakeTask('t1');
        $task2 = new ResourceQuotaFakeTask('t2');

        self::assertSame('first', $checker->process($task1, fn (): string => 'first'));
        self::assertSame('second', $checker->process($task2, fn (): string => 'second'));
    }

    public function testExceptionInNextStillReleasesSlot(): void
    {
        $checker = new ResourceQuotaChecker(1);
        $task1 = new ResourceQuotaFakeTask('t1');
        $task2 = new ResourceQuotaFakeTask('t2');

        $this->expectException(RuntimeException::class);
        $checker->process($task1, function (): string {
            throw new RuntimeException('boom');
        });

        self::assertSame('ok', $checker->process($task2, fn (): string => 'ok'));
    }
}

final readonly class ResourceQuotaFakeTask implements TaskInterface
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
        return new ResourceQuotaFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new ResourceQuotaFakeCancellationScope();
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

final class ResourceQuotaFakeExecutionId implements ExecutionIdInterface
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

final class ResourceQuotaFakeCancellationScope implements CancellationScopeInterface
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
