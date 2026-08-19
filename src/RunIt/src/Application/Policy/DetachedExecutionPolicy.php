<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

/**
 * Nested-execution policy that runs a nested call as a detached root execution.
 *
 * When no execution is active the call becomes a root execution. When one is active the
 * call becomes a new root execution whose linked-execution identity references the
 * current execution, so the two stay correlated without forming a parent/child tree.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DetachedExecutionPolicy implements NestedExecutionPolicyInterface
{
    public function resolve(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition): NestedExecutionMode
    {
        if ($current === null) {
            return NestedExecutionMode::ChildExecution;
        }

        return NestedExecutionMode::DetachedExecution;
    }
}
