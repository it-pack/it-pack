<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Scheduler contract used by the RunIt execution engine.
 *
 * Extends {@see SchedulerInterface} with the single-operation dispatch the
 * {@see \ItPack\RunIt\Contract\Application\ExecutionEngineInterface} needs: run
 * one operation to completion and return its value. The additional method is a
 * focused extension rather than a modification of the shared scheduling
 * contract, so third-party schedulers opt in only when used as an engine.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
interface EngineSchedulerInterface extends SchedulerInterface
{
    /**
     * Queues the operation, drains the reactor until it terminates, and returns
     * the operation's result (whatever it is, including null).
     */
    public function dispatch(TaskInterface $task, callable $operation): mixed;
}
