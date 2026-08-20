<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Fiber;

use Fiber;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Runs the operation inside a dedicated Fiber.
 *
 * The operation executes on its own Fiber call stack, so it may suspend and resume without
 * affecting the caller. The returned value is delivered back to the caller once the Fiber
 * completes. Exceptions propagate out of the Fiber to the runner unchanged.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberExecutionEngine implements ExecutionEngineInterface
{
    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        $fiber = new Fiber(static function () use ($operation, $scope): void {
            Fiber::suspend($operation($scope));
        });

        $result = $fiber->start();
        $fiber->resume();

        return $result;
    }

    public function capability(): EngineCapabilityInterface
    {
        return new \ItPack\RunIt\Contract\Application\EngineCapability(true, false, false);
    }
}
