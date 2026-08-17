<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the immutable blueprint of an execution.
 *
 * A definition carries the name, kind, optional parent and linked execution
 * references, and the initial execution context. It is the input to an execution
 * runner and is never mutated as the execution runs.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionDefinitionInterface extends NormalizableInterface
{
    public function name(): ExecutionNameInterface;

    public function kind(): ExecutionKindInterface;

    /**
     * Returns the parent execution identifier, or null when this is a root execution.
     */
    public function parentExecutionId(): ?ExecutionIdInterface;

    /**
     * Returns additional executions linked to this one (for example correlated work).
     *
     * @return list<ExecutionIdInterface>
     */
    public function linkedExecutionIds(): array;

    public function context(): ExecutionContextInterface;
}
