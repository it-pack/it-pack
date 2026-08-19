<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

/**
 * Nested-execution policy that rejects a nested call outright.
 *
 * When no execution is active the call becomes a root execution. When one is active the
 * policy refuses to start a nested execution, leaving the decision to fail loudly rather
 * than silently restructure the call.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RejectNestedPolicy implements NestedExecutionPolicyInterface
{
    public function resolve(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition): NestedExecutionMode
    {
        if ($current === null) {
            return NestedExecutionMode::ChildExecution;
        }

        return NestedExecutionMode::RejectNested;
    }
}
