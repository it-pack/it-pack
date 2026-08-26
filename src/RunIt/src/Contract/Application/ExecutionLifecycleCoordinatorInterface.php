<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;

/**
 * Contract for enforcing the execution lifecycle state machine.
 *
 * The coordinator owns the allowed transition matrix, rejects transitions out of
 * terminal states, and defines both idempotent (same status repeated) and
 * conflicting (different terminal status repeated) completion behavior.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionLifecycleCoordinatorInterface
{
    /**
     * Completes the execution with the given result, returning the new record.
     *
     * The target status is taken from the result. The transition is validated
     * against the lifecycle matrix; a terminal execution completed again with the
     * same status is returned unchanged (idempotent), while a different terminal
     * status raises a conflicting-completion error.
     *
     * @throws LifecycleExceptionInterface when the
     *        transition is illegal, or when a terminal execution is completed again
     *        with a different terminal status
     */
    public function transition(
        ExecutionInterface $execution,
        ExecutionResultInterface $result,
        InstantInterface $endedAt
    ): ExecutionInterface;

    /**
     * Moves a created execution into the running state, returning the new record.
     *
     * @throws LifecycleExceptionInterface when the
     *        execution is not in the created state
     */
    public function start(ExecutionInterface $execution): ExecutionInterface;
}
