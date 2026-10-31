<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\SchedulerEventType;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\SchedulerEventInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * Value object capturing a lifecycle event emitted by the scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-resumed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-suspended
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-completed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-cancellation-observed
 * @api
 */
final readonly class SchedulerEvent implements SchedulerEventInterface
{
    private function __construct(
        public TaskInterface $task,
        public SchedulerEventType $type,
        public TaskStatus $status,
        public ?AwaitPoint $awaitPoint = null,
        public ?TaskIdInterface $parentId = null,
    ) {
    }

    public static function spawned(TaskInterface $task, ?TaskIdInterface $parentId = null): self
    {
        return new self($task, SchedulerEventType::Spawned, TaskStatus::Created, parentId: $parentId);
    }

    public static function resumed(TaskInterface $task, ?AwaitPoint $awaitPoint = null): self
    {
        return new self($task, SchedulerEventType::Resumed, TaskStatus::Running, $awaitPoint);
    }

    public static function suspended(TaskInterface $task, AwaitPoint $awaitPoint): self
    {
        return new self($task, SchedulerEventType::Suspended, TaskStatus::Running, $awaitPoint);
    }

    public static function completed(TaskInterface $task, TaskStatus $status): self
    {
        return new self($task, SchedulerEventType::Completed, $status);
    }

    public static function cancellationObserved(TaskInterface $task): self
    {
        return new self($task, SchedulerEventType::Completed, TaskStatus::Cancelled);
    }
}
