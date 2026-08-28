<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Application\Scheduler\TaskRecord;

/**
 * Creates the per-task {@see TaskRecord} the reactor books Fiber/handle state against.
 *
 * Lives in the `Factory` namespace so the scheduler can obtain records via DI
 * without calling `new` itself (per `ForbidNewOutsideFactoryBuilderRule`).
 *
 * @see https://w3id.org/itpack/asyncit#Task
 */
interface TaskRecordFactoryInterface
{
    /**
     * @param \Fiber<mixed, mixed, mixed, mixed> $fiber
     */
    public function create(TaskInterface $task, Fiber $fiber): TaskRecord;
}
