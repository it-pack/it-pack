<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Behavior;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\AsyncIt\Domain\Exception\ConflictingCompletionException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

trait TaskBehavior
{
    public readonly TaskId $id;

    public readonly ExecutionIdInterface $executionId;

    public readonly CancellationScopeInterface $cancellationScope;

    public TaskStatus $status {
        get {
            return $this->_status;
        }
        set {
            $this->transitionTo($value);
        }
    }

    private TaskStatus $_status = TaskStatus::Created;

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
        return $this->cancellationScope;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function transitionTo(TaskStatus $next): void
    {
        if ($next === $this->status()) {
            return;
        }

        if (!$this->status()->isTerminal() && !$this->status()->canTransitionTo($next)) {
            throw new InvalidLifecycleTransitionException($this->id, $this->status(), $next);
        }

        if ($this->status()->isTerminal()) {
            if ($next->isTerminal()) {
                throw new ConflictingCompletionException($this->id, $this->status(), $next);
            }

            throw new InvalidLifecycleTransitionException($this->id, $this->status(), $next);
        }

        $this->setStatus($next);
    }

    public function complete(TaskStatus $outcome): void
    {
        $this->transitionTo($outcome);
    }

    private function setStatus(TaskStatus $status): void
    {
        $this->_status = $status;
    }
}
