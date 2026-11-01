<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\ResultTypedSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use LogicException;
use Throwable;

/**
 * Canary deployment scheduler that wraps a baseline scheduler and a canary
 * scheduler, routing a configured fraction of dispatches to the canary.
 *
 * Tracks error and latency metrics for both paths and auto-rolls-back to
 * 100% baseline when the canary breaches the relative error-rate or
 * latency-factor threshold versus the baseline. All non-dispatch methods
 * delegate to the baseline scheduler.
 *
 * Canary routing uses a cryptographically secure draw so traffic-splitting
 * decisions cannot be predicted or manipulated by an observer.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final class CanaryScheduler implements EngineSchedulerInterface, ResultTypedSchedulerInterface
{
    public const float MAX_PROBABILITY = 1.0;

    public const float MIN_LATENCY_FACTOR = 1.0;

    /**
     * @var array{
     *   calls: int,
     *   errors: int,
     *   totalLatencyNanos: int,
     * }
     */
    private array $baselineMetrics = ['calls' => 0, 'errors' => 0, 'totalLatencyNanos' => 0];

    /**
     * @var array{
     *   calls: int,
     *   errors: int,
     *   totalLatencyNanos: int,
     * }
     */
    private array $canaryMetrics = ['calls' => 0, 'errors' => 0, 'totalLatencyNanos' => 0];

    private bool $rolledBack = false;

    public function __construct(
        private readonly EngineSchedulerInterface $baseline,
        private readonly EngineSchedulerInterface $canary,
        private readonly float $canaryWeight,
        private readonly float $errorMargin,
        private readonly float $latencyFactor,
        private readonly int $minCalls = 10,
    ) {
        $this->validateCanaryWeight($canaryWeight);
        $this->validateErrorMargin($errorMargin);
        $this->validateLatencyFactor($latencyFactor);
        $this->validateMinCalls($minCalls);
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $this->baseline->submit($task, $operation);
    }

    public function run(): void
    {
        $this->baseline->run();
    }

    public function result(TaskInterface $task): mixed
    {
        return $this->baseline->result($task);
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        if ($this->rolledBack) {
            return $this->baseline->dispatch($task, $operation);
        }

        $useCanary = random_int(0, \mt_getrandmax()) / \mt_getrandmax() < $this->canaryWeight;
        $scheduler = $useCanary ? $this->canary : $this->baseline;

        $startNanos = hrtime(as_number: true);
        try {
            $result = $scheduler->dispatch($task, $operation);
            $metricsRef = &$this->baselineMetrics;
            if ($useCanary) {
                $metricsRef = &$this->canaryMetrics;
            }

            $this->incrementMetrics($metricsRef, hrtime(as_number: true) - $startNanos, isError: false);
            $this->checkRollback();

            return $result;
        } catch (Throwable $throwable) {
            $metricsRef = &$this->baselineMetrics;
            if ($useCanary) {
                $metricsRef = &$this->canaryMetrics;
            }

            $this->incrementMetrics($metricsRef, hrtime(as_number: true) - $startNanos, isError: true);
            $this->checkRollback();

            throw $throwable;
        }
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        return $this->baseline->spawn($operation, $id);
    }

    public function await(TaskInterface $task): mixed
    {
        return $this->baseline->await($task);
    }

    public function currentTask(): ?TaskInterface
    {
        return $this->baseline->currentTask();
    }

    public function awaitDelay(DurationInterface $duration): void
    {
        $this->baseline->awaitDelay($duration);
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        return $this->baseline->retry($operation, $policy);
    }

    public function health(): SchedulerHealthInterface
    {
        return $this->baseline->health();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->baseline->swapCapabilities($descriptor);
        $this->canary->swapCapabilities($descriptor);
    }

    public function resultTyped(TaskInterface $task): TaskResultInterface
    {
        if ($this->baseline instanceof ResultTypedSchedulerInterface) {
            try {
                return $this->baseline->resultTyped($task);
            } catch (LogicException $baselineError) {
                if ($this->canary instanceof ResultTypedSchedulerInterface) {
                    try {
                        return $this->canary->resultTyped($task);
                    } catch (LogicException) {
                        throw $baselineError;
                    }
                }

                throw $baselineError;
            }
        }

        if ($this->canary instanceof ResultTypedSchedulerInterface) {
            return $this->canary->resultTyped($task);
        }

        throw new LogicException('Neither baseline nor canary scheduler supports resultTyped().');
    }

    /**
     * Returns the canary routing weight (0.0 = none, 1.0 = all).
     */
    public function canaryWeight(): float
    {
        return $this->canaryWeight;
    }

    /**
     * Whether the canary has been rolled back to 100% baseline.
     */
    public function isRolledBack(): bool
    {
        return $this->rolledBack;
    }

    /**
     * Re-enables the canary path after a rollback, clearing the rolled-back
     * flag so future dispatches can route to the canary again.
     */
    public function resetRollback(): void
    {
        $this->rolledBack = false;
    }

    /**
     * @return array{baseline: array{calls:int, errors:int, totalLatencyNanos:int},
     *           canary: array{calls:int, errors:int, totalLatencyNanos:int}}
     */
    public function metrics(): array
    {
        return [
            'baseline' => $this->baselineMetrics,
            'canary' => $this->canaryMetrics,
        ];
    }

    /**
     * Increments a metrics bucket by one call, adding latency and optionally
     * an error count. Shared by both canary and baseline paths to avoid
     * duplicated increment logic.
     *
     * @param array{calls:int, errors:int, totalLatencyNanos:int} $metrics
     */
    private function incrementMetrics(array &$metrics, int $latencyNanos, bool $isError): void
    {
        $metrics['calls']++;
        $metrics['totalLatencyNanos'] += $latencyNanos;
        if ($isError) {
            $metrics['errors']++;
        }
    }

    private function checkRollback(): void
    {
        if ($this->rolledBack) {
            return;
        }

        $canary = $this->canaryMetrics;
        $baseline = $this->baselineMetrics;

        if ($canary['calls'] < $this->minCalls || $baseline['calls'] < $this->minCalls) {
            return;
        }

        $this->checkErrorRateRollback($canary, $baseline);
        $this->checkLatencyRollback($canary, $baseline);
    }

    /**
     * @param array{calls:int, errors:int, totalLatencyNanos:int} $canary
     * @param array{calls:int, errors:int, totalLatencyNanos:int} $baseline
     */
    private function checkErrorRateRollback(array $canary, array $baseline): void
    {
        $canaryErrorRate = $canary['errors'] / $canary['calls'];
        $baselineErrorRate = $baseline['errors'] / $baseline['calls'];
        $errorMargin = $this->errorMargin;

        if ($canaryErrorRate > $baselineErrorRate + $errorMargin) {
            $this->rolledBack = true;
        }
    }

    /**
     * @param array{calls:int, errors:int, totalLatencyNanos:int} $canary
     * @param array{calls:int, errors:int, totalLatencyNanos:int} $baseline
     */
    private function checkLatencyRollback(array $canary, array $baseline): void
    {
        $canaryAvgLatency = $canary['totalLatencyNanos'] / $canary['calls'];
        $baselineAvgLatency = $baseline['totalLatencyNanos'] / $baseline['calls'];
        $latencyFactor = $this->latencyFactor;

        if ($canaryAvgLatency > $baselineAvgLatency * $latencyFactor) {
            $this->rolledBack = true;
        }
    }

    private function validateCanaryWeight(float $weight): void
    {
        if ($weight < 0.0 || $weight > self::MAX_PROBABILITY) {
            throw new InvalidArgumentException(
                'canaryWeight must be between 0.0 and 1.0, ' . $weight . ' given.',
            );
        }
    }

    private function validateErrorMargin(float $margin): void
    {
        if ($margin < 0.0 || $margin > self::MAX_PROBABILITY) {
            throw new InvalidArgumentException(
                'errorMargin must be between 0.0 and 1.0, ' . $margin . ' given.',
            );
        }
    }

    private function validateLatencyFactor(float $factor): void
    {
        if ($factor < self::MIN_LATENCY_FACTOR) {
            throw new InvalidArgumentException(
                'latencyFactor must be >= 1.0, ' . $factor . ' given.',
            );
        }
    }

    private function validateMinCalls(int $minCalls): void
    {
        if ($minCalls < 1) {
            throw new InvalidArgumentException(
                'minCalls must be >= 1, ' . $minCalls . ' given.',
            );
        }
    }
}
