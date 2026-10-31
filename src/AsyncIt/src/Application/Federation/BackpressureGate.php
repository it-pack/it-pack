<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;

/**
 * Applies a backpressure policy when the pipeline is at capacity.
 *
 * Tracks active dispatches. When the count reaches {@see $maxConcurrency}:
 * - {@see BackpressurePolicy::Error} throws {@see BackpressureExceededException}.
 * - {@see BackpressurePolicy::Drop} throws {@see BackpressureExceededException}
 *   instead of silently returning null, so dropped tasks are observable.
 * - {@see BackpressurePolicy::Block} throws {@see BackpressureExceededException}
 *   at capacity; true blocking requires scheduler-level admission control and
 *   is not implemented at the middleware layer.
 *
 * Below capacity, the active counter is incremented before invoking the next
 * middleware and decremented in finally, so concurrent dispatch counting is
 * always accurate regardless of policy.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-block
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-drop
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-error
 */
final class BackpressureGate implements SchedulerMiddlewareInterface
{
    private int $active = 0;

    public function __construct(private readonly BackpressurePolicy $policy, private readonly int $maxConcurrency)
    {
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        if ($this->active < $this->maxConcurrency) {
            $this->active++;

            try {
                return $next();
            } finally {
                $this->active--;
            }
        }

        return match ($this->policy) {
            BackpressurePolicy::Error => throw new BackpressureExceededException(
                'Backpressure gate closed: ' . $this->active . ' concurrent tasks, limit '
                . $this->maxConcurrency . '.',
            ),
            BackpressurePolicy::Drop => throw new BackpressureExceededException(
                'Backpressure gate dropped task: ' . $this->active . ' concurrent tasks, limit '
                . $this->maxConcurrency . '.',
            ),
            BackpressurePolicy::Block => throw new BackpressureExceededException(
                'Backpressure gate blocked at capacity: ' . $this->active . ' concurrent tasks, limit '
                . $this->maxConcurrency . '. True blocking requires scheduler-level admission control.',
            ),
        };
    }
}
