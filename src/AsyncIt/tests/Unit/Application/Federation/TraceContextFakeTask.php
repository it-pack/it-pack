<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

final readonly class TraceContextFakeTask implements TaskInterface
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
        return new TraceContextFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new TraceContextFakeCancellationScope();
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
