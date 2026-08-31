<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * The scheduling engine contract.
 *
 * The scheduler coordinates task queues, Fiber suspension/resumption, timers,
 * and cancellation. It owns *how* work progresses; it never owns execution
 * semantics (RunIt does) and never constructs collaborators directly — they are
 * injected (see the `*Factory*` contracts in `Application\Factory`).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
  * @api

  */
interface SchedulerInterface
{
    /**
     * Queues a task together with the operation its Fiber will run.
     */
    public function submit(TaskInterface $task, callable $operation): void;

    /**
     * Drains the reactor: fire ready tasks, expire timers (monotonic), wait on
     * the poller, and repeat until idle.
     */
    public function run(): void;
}
