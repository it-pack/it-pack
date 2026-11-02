<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use Throwable;

/**
 * Publishes scheduler lifecycle artifacts into the RunIt artifact stream.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface TaskLifecyclePublisherInterface
{
    public function publishSpawned(TaskInterface $task): void;

    public function publishResumed(TaskInterface $task, ?int $startTimeNanos = null): void;

    public function publishCompleted(
        TaskInterface $task,
        TaskStatus $status,
        ?int $startTimeNanos = null,
        ?Throwable $throwable = null,
    ): void;

    public function publishFailure(TaskInterface $task, Throwable $throwable): void;
}
