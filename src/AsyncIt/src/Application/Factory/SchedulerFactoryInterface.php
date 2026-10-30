<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Builds an {@see EngineSchedulerInterface} bound to one execution scope.
 *
 * The engine attaches the caller-supplied RunIt scope around each resume, so the
 * scheduler is assembled per {@see \ItPack\RunIt\Contract\Application\ExecutionEngineInterface::run()}
 * call with a scope factory that yields exactly that scope.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
interface SchedulerFactoryInterface
{
    public function create(ExecutionScopeInterface $scope, ExecutionIdInterface $executionId): EngineSchedulerInterface;
}
