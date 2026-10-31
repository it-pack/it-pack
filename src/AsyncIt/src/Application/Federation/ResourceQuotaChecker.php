<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;

/**
 * Enforces a hard resource quota on concurrent task dispatches.
 *
 * Tracks the number of tasks currently flowing through the pipeline. If the
 * count reaches {@see $maxConcurrency}, {@see BackpressureExceededException}
 * is raised before the next middleware or scheduler is invoked.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 */
final class ResourceQuotaChecker implements SchedulerMiddlewareInterface
{
    private int $active = 0;

    public function __construct(private readonly int $maxConcurrency)
    {
        if ($maxConcurrency <= 0) {
            throw new InvalidArgumentException(
                'maxConcurrency must be greater than 0, ' . $maxConcurrency . ' given.',
            );
        }
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        if ($this->active >= $this->maxConcurrency) {
            throw new BackpressureExceededException(
                'Resource quota exceeded: ' . $this->active . ' concurrent tasks, limit '
                . $this->maxConcurrency . '.',
            );
        }

        $this->active++;

        try {
            return $next();
        } finally {
            $this->active--;
        }
    }
}
