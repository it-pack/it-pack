<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use Throwable;

/**
 * Contract for running one activity inside an execution.
 *
 * The activity runner creates an activity bound to the ambient execution, attaches
 * an {@see ActivityScopeInterface}, invokes the operation, completes the activity,
 * and restores the previous activity context. The operation receives the activity
 * scope and returns its own result unchanged on success.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivityRunnerInterface
{
    /**
     * Runs the operation as an activity and returns its unchanged result.
     *
     * @template TResult
     *
     * @param callable(ActivityScopeInterface): TResult $operation the consumer operation
     *
     * @return TResult
     *
     * @throws Throwable the exact throwable raised by the operation, after the
     *                    activity has been completed as failed
     */
    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed;
}
