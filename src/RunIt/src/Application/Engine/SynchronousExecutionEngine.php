<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Engine;

use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Default execution engine: runs the operation on the calling call stack.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SynchronousExecutionEngine implements ExecutionEngineInterface
{
    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        return $operation($scope);
    }

    public function capability(): EngineCapabilityInterface
    {
        return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: false);
    }
}
