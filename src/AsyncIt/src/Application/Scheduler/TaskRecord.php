<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * Per-task bookkeeping for the reactor: the task, its Fiber, any poll handles
 * it registered, and its terminal state.
 *
 * @see https://w3id.org/itpack/asyncit#Task
  * @api

  */
final class TaskRecord implements TaskRecordInterface
{
    /**
     * @param list<StreamPollHandleInterface> $handles
     * @param Fiber<mixed, mixed, mixed, mixed> $fiber
     */
    public function __construct(
        private readonly TaskInterface $task,
        private readonly Fiber $fiber,
        private array $handles = [],
        private bool $done = false,
    ) {
    }

    public function task(): TaskInterface
    {
        return $this->task;
    }

    /**
     * @return Fiber<mixed, mixed, mixed, mixed>
     */
    public function fiber(): Fiber
    {
        return $this->fiber;
    }

    /**
     * @return list<StreamPollHandleInterface>
     */
    public function handles(): array
    {
        return $this->handles;
    }

    public function addHandle(StreamPollHandleInterface $handle): void
    {
        $this->handles[] = $handle;
    }

    public function clearHandles(): void
    {
        $this->handles = [];
    }

    public function markDone(): void
    {
        $this->done = true;
    }

    public function isDone(): bool
    {
        return $this->done;
    }
}
