<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

/**
 * Default nested-execution policy.
 *
 * When no execution is active the call becomes a root execution. When one is active the
 * call becomes a child execution linked to its parent by identity, so the parent/child
 * relationship is explicit and correlation across executions is preserved.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ChildExecutionPolicy implements NestedExecutionPolicyInterface
{
    public function resolve(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition): NestedExecutionMode
    {
        return NestedExecutionMode::ChildExecution;
    }
}
