<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;

/**
 * Contract for deciding how a nested execution call is handled.
 *
 * When an execution is already active (the operation re-enters the runner), the
 * policy chooses whether to create a child execution, run an activity, create a
 * detached execution linked to the current one, or reject the nested call. When no
 * execution is active, the policy always yields a root execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface NestedExecutionPolicyInterface
{
    public function resolve(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition): NestedExecutionMode;
}
