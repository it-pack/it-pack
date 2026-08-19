<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

/**
 * Nested-execution policy that runs a nested call as an activity of the current execution.
 *
 * When no execution is active the call becomes a root execution. When one is active the
 * call is represented as an activity rather than a child execution, which keeps a single
 * execution record and is appropriate for lightweight, nested units of work.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class ActivityOnlyPolicy implements NestedExecutionPolicyInterface
{
    public function resolve(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition): NestedExecutionMode
    {
        if ($current === null) {
            return NestedExecutionMode::ChildExecution;
        }

        return NestedExecutionMode::ActivityOnly;
    }
}
