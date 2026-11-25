<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Runner;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use Throwable;

/**
 * Contract for running one execution from a definition.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface DefaultExecutionRunnerInterface
{
    /**
     * @template TResult
     *
     * @param callable(ExecutionScopeInterface): TResult $operation
     *
     * @return TResult
     *
     * @throws Throwable
     */
    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed;

    public function activityContextPolicy(): string;
}
