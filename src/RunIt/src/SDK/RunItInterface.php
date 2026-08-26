<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK;

use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface;

/**
 * Ergonomic, interface-backed entry point to a RunIt runtime.
 *
 * The facade delegates to application-layer contracts and never exposes concrete services. It
 * supports running an execution directly and wrapping existing callables so they execute within a
 * RunIt execution without changing their code.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RunItInterface
{
    /**
     * Runs an operation inside an execution and returns its result unchanged.
     *
     * @param callable(ExecutionScopeInterface): mixed $operation the work to perform within the execution scope
     */
    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed;

    /**
     * Returns the currently active execution scope, or null when none is active.
     */
    public function currentExecution(): ?ExecutionScopeInterface;

    /**
     * Exposes the underlying execution runner for advanced use.
     */
    public function executionRunner(): ExecutionRunnerInterface;

    /**
     * Wraps a callable so every invocation runs inside an execution.
     *
     * Provide a static {@see ExecutionDefinitionInterface} or a factory
     * `callable(array): ExecutionDefinitionInterface`. When omitted, an {@see \ItPack\RunIt\SDK\Attribute\ExecutedAs}
     * attribute on the callable is used if present.
     *
     * @param ExecutionDefinitionInterface|callable(array<mixed>): ExecutionDefinitionInterface|null $definition
     */
    public function wrap(
        callable $operation,
        ExecutionDefinitionInterface|callable|null $definition = null
    ): ExecutedCallableInterface;
}
