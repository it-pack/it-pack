<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * Per-task bookkeeping surface for the reactor: the task, its Fiber, any poll
 * handles it registered, and its terminal state.
 *
 * Backs every non-`__` method on {@see TaskRecord} so records remain mockable.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @api
 */
interface TaskRecordInterface
{
    /**
     * Returns the task this record wraps.
     */
    public function task(): TaskInterface;

    /**
     * Returns the underlying Fiber.
     *
     * @return Fiber<mixed, mixed, mixed, mixed>
     */
    public function fiber(): Fiber;

    /**
     * Returns the poll handles registered for this task.
     *
     * @return list<StreamPollHandleInterface>
     */
    public function handles(): array;

    /**
     * Registers a poll handle for this task.
     */
    public function addHandle(StreamPollHandleInterface $handle): void;

    /**
     * Clears all registered poll handles for this task.
     */
    public function clearHandles(): void;

    /**
     * Marks this record as terminally done.
     */
    public function markDone(): void;

    /**
     * Whether this record has reached a terminal state.
     */
    public function isDone(): bool;
}
