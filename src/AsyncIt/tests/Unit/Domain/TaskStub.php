<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Minimal TaskInterface stub for tests that trigger TaskGroup::__destruct.
 */
final class TaskStub implements TaskInterface
{
    public function __construct(
        private readonly TaskId $id,
        private readonly ExecutionIdInterface $executionId,
        private TaskStatus $status,
        private readonly CancellationScopeInterface $scope,
    ) {
    }

    public function id(): TaskId
    {
        return $this->id;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return $this->scope;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function transitionTo(TaskStatus $next): void
    {
        $this->status = $next;
    }

    public function complete(TaskStatus $outcome): void
    {
        $this->status = $outcome;
    }
}
