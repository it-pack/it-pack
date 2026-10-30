<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskGroupInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Behavior\TaskBehavior;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use Throwable;

/**
 * Structured-concurrency task group: spawns children that share a cancellation
 * scope and runs to completion (or failure) as a unit.
 *
 * On any child failure, the group cancels its scope (cascading to siblings)
 * and propagates the first failure once all children have unwound. The group
 * itself is a {@see TaskInterface} so it can be awaited by a parent.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-first-failure
 */
final class TaskGroup implements TaskGroupInterface
{
    use TaskBehavior;

    private bool $closed = false;

    /**
     * @var list<TaskInterface>
     */
    private array $children = [];

    private ?Throwable $failure = null;

    private int $runningCount = 0;

    public function __construct(
        TaskId $id,
        ExecutionIdInterface $executionId,
        CancellationScopeInterface $cancellationScope,
        private readonly EngineSchedulerInterface $scheduler,
        private readonly int $maxConcurrency = 0,
        private readonly BackpressurePolicy $backpressurePolicy = BackpressurePolicy::Block,
    ) {
        $this->id = $id;
        $this->executionId = $executionId;
        $this->cancellationScope = $cancellationScope;
    }

    /**
     * Auto-cancels any still-pending children when the group goes out of scope
     * without being explicitly closed or completed. This guarantees structured
     * concurrency: leaked groups never leave child tasks running indefinitely.
     */
    public function __destruct()
    {
        if ($this->children === [] || $this->status()->isTerminal()) {
            return;
        }

        foreach ($this->children as $child) {
            if (!$child->status()->isTerminal()) {
                $child->cancellationScope()->cancel(CancellationReason::ParentCancelled);
            }
        }
    }

    public function failure(): ?Throwable
    {
        return $this->failure;
    }

    /**
     * Records the first failure observed among the group's children.
     */
    public function fail(Throwable $failure): void
    {
        $this->failure ??= $failure;
    }

    public function go(callable $operation): TaskInterface
    {
        if ($this->closed) {
            throw new InvalidLifecycleTransitionException(
                $this->id,
                TaskStatus::Running,
                TaskStatus::Cancelled,
            );
        }

        if ($this->maxConcurrency > 0 && $this->runningCount >= $this->maxConcurrency) {
            if ($this->backpressurePolicy === BackpressurePolicy::Drop) {
                return $this->createNoOpChild();
            }

            if ($this->backpressurePolicy === BackpressurePolicy::Error) {
                throw new BackpressureExceededException('maxConcurrency limit reached (policy: error)');
            }

            $this->waitForSlot();
        }

        $child = $this->scheduler->spawn($operation);
        $this->children[] = $child;
        $this->runningCount++;

        $this->cancellationScope->onCancel(static function () use ($child): void {
            $child->cancellationScope()->cancel(CancellationReason::ParentCancelled);
        });

        return $child;
    }

    public function await(): array
    {
        if ($this->children === []) {
            return [];
        }

        $results = [];

        foreach ($this->children as $child) {
            $results[] = $this->scheduler->await($child);
        }

        $this->runningCount = 0;

        return $results;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function status(): TaskStatus
    {
        if ($this->failure instanceof Throwable && $this->status !== TaskStatus::Failed) {
            return TaskStatus::Failed;
        }

        return $this->status;
    }

    public function backpressurePolicy(): BackpressurePolicy
    {
        return $this->backpressurePolicy;
    }

    private function waitForSlot(): void
    {
        if ($this->children === []) {
            return;
        }

        $this->scheduler->await($this->children[0]);
        $this->runningCount--;
    }

    private function createNoOpChild(): TaskInterface
    {
        return $this->scheduler->spawn(static fn (): null => null);
    }
}
