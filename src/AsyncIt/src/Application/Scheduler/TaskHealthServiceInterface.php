<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use Throwable;

/**
 * Captures scheduler health snapshots.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
interface TaskHealthServiceInterface
{
    /**
     * @param list<TaskRecordInterface> $records
     * @param list<Throwable> $errors
     */
    public function capture(array $records, int $startTimeNanos, array $errors): SchedulerHealthInterface;
}
