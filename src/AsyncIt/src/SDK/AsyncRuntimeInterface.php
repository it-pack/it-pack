<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK;

use Generator;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\CapabilityObserverInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskStreamInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * AsyncIt's reference RunIt execution engine surface.
 *
 * Backs every public method on {@see AsyncRuntime} so the runtime remains
 * mockable and the scheduling contracts stay interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface AsyncRuntimeInterface extends ExecutionEngineInterface, CapabilityObserverInterface
{
    /**
     * Returns the task channel for the negotiated runtime adapter.
     */
    public function channel(): TaskChannelInterface;

    /**
     * Returns the telemetry bridge for the negotiated runtime adapter.
     */
    public function telemetry(): TelemetryBridgeInterface;

    /**
     * Executes the supplied operation inside the reference reactor and returns
     * its value.
     */
    public function run(ExecutionScopeInterface $scope, callable $operation): mixed;

    /**
     * Spawns a new task on the active scheduler.
     */
    public function spawn(callable $operation, TaskIdInterface|null $id = null): TaskInterface;

    /**
     * Awaits the result of a previously spawned task.
     */
    public function await(TaskInterface $task): mixed;

    /**
     * Suspends the current task for a monotonic duration.
     */
    public function awaitDelay(DurationInterface $duration): void;

    /**
     * Runs all operations concurrently and returns the result of the first to
     * complete. Cancels remaining operations.
     *
     * @param list<callable> $operations
     */
    public function race(array $operations): mixed;

    /**
     * Runs all operations concurrently and returns all results in spawn order.
     *
     * @param list<callable> $operations
     * @return list<mixed>
     */
    public function all(array $operations): array;

    /**
     * Runs all operations concurrently and returns the result of the first to
     * succeed. Ignores failures of other children; throws if all fail.
     *
     * @param list<callable> $operations
     */
    public function any(array $operations): mixed;

    /**
     * Runs all operations concurrently and returns the winning task and its
     * result.
     *
     * @param list<callable> $operations
     * @return array{task: TaskInterface|null, result: mixed}
     */
    public function select(array $operations): array;

    /**
     * Runs all operations concurrently and waits for all to complete.
     *
     * @param list<callable> $operations
     */
    public function join(array $operations): void;

    /**
     * Lazily streams tasks from a generator, one at a time.
     *
     * @param callable(): Generator $generator
     */
    public function stream(callable $generator): TaskStreamInterface;

    /**
     * Returns a health snapshot of the scheduler's current runtime state.
     */
    public function health(): ?SchedulerHealthInterface;

    /**
     * Reports the negotiated engine capability.
     */
    public function capability(): EngineCapabilityInterface;

    /**
     * Reacts to observed capability changes from the runtime adapter.
     */
    public function onCapabilitiesChanged(CapabilityDescriptorInterface $descriptor): void;

    /**
     * Discovers and assembles the runtime, returning a ready-to-run engine.
     */
    public static function discover(): self;
}
