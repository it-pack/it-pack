<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

/**
 * Executes a consumer operation within a running execution.
 *
 * The runner owns execution semantics (lifecycle, identity, artifacts); the engine owns
 * only *how* the operation is run — synchronously, inside a Fiber, or inside a coroutine.
 * Engines never introduce a proprietary promise or scheduler contract.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionEngineInterface
{
    /**
     * Runs the operation, returning whatever it returns.
     */
    public function run(ExecutionScopeInterface $scope, callable $operation): mixed;

    /**
     * Describes what this engine can do, for capability-aware composition.
     */
    public function capability(): EngineCapabilityInterface;
}
