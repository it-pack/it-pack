<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\ResultTypedSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Canary deployment scheduler surface.
 *
 * Wraps a baseline scheduler and a canary scheduler, routing a configured
 * fraction of dispatches to the canary while tracking error and latency
 * metrics for both paths. Backs {@see CanaryScheduler}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface CanarySchedulerInterface extends EngineSchedulerInterface, ResultTypedSchedulerInterface
{
    /**
     * Returns the canary routing weight (0.0 = none, 1.0 = all).
     */
    public function canaryWeight(): float;

    /**
     * Whether the canary has been rolled back to 100% baseline.
     */
    public function isRolledBack(): bool;

    /**
     * Re-enables the canary path after a rollback.
     */
    public function resetRollback(): void;

    /**
     * Returns the canary and baseline metrics.
     *
     * @return array{baseline: array{calls:int, errors:int, totalLatencyNanos:int},
     *           canary: array{calls:int, errors:int, totalLatencyNanos:int}}
     */
    public function metrics(): array;

    public function submit(TaskInterface $task, callable $operation): void;

    public function run(): void;

    public function result(TaskInterface $task): mixed;

    public function dispatch(TaskInterface $task, callable $operation): mixed;

    public function spawn(callable $operation, TaskIdInterface|null $id = null): TaskInterface;

    public function await(TaskInterface $task): mixed;

    public function currentTask(): ?TaskInterface;

    public function awaitDelay(DurationInterface $duration): void;

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed;

    public function health(): SchedulerHealthInterface;

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void;

    public function resultTyped(TaskInterface $task): TaskResultInterface;
}
