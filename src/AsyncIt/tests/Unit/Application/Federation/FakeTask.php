<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

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
