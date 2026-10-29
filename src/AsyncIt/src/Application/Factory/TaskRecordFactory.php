<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;
use ItPack\AsyncIt\Application\Scheduler\TaskRecord;
use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * Concrete {@see TaskRecordFactoryInterface} building a {@see TaskRecord}.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 */
final class TaskRecordFactory implements TaskRecordFactoryInterface
{
    /**
     * @param \Fiber<mixed, mixed, mixed, mixed> $fiber
     */
    public function create(TaskInterface $task, Fiber $fiber): TaskRecord
    {
        return new TaskRecord($task, $fiber);
    }
}
