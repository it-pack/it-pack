<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

/**
 * Contract for running one execution from a definition.
 *
 * The runner owns the execution lifecycle: it validates the definition, allocates
 * identity and sequence, attaches an {@see ExecutionScopeInterface} to context
 * storage, invokes the consumer operation, records the outcome, and always
 * restores the prior context. The operation receives the live scope and returns
 * its own result unchanged on success; on failure the runner records the failure
 * and rethrows the exact original throwable.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionRunnerInterface
{
    /**
     * Runs the operation as an execution and returns its unchanged result.
     *
     * @template TResult
     *
     * @param callable(ExecutionScopeInterface): TResult $operation the consumer operation
     *
     * @return TResult
     *
     * @throws \Throwable the exact throwable raised by the operation, after the
     *                    failure has been recorded and the context restored
     */
    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed;
}
