<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Application\SchedulerHealth;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use Throwable;

/**
 * Captures scheduler health snapshots.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
final readonly class TaskHealthService implements TaskHealthServiceInterface
{
    public function __construct(
        private PollerPortInterface $poller,
        private MonotonicClockInterface $clock,
    ) {
    }

    /**
     * @param list<TaskRecordInterface> $records
     * @param list<Throwable> $errors
     */
    public function capture(array $records, int $startTimeNanos, array $errors): SchedulerHealthInterface
    {
        return SchedulerHealth::capture(
            $records,
            $this->poller->activeHandleCount(),
            $this->clock,
            $startTimeNanos,
            $errors,
        );
    }
}
