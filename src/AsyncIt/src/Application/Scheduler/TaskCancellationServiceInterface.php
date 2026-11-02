<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

/**
 * Cancellation scope management for the reactor.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
interface TaskCancellationServiceInterface
{
    /**
     * @param list<TaskRecordInterface> $records
     * @return list<TaskRecordInterface>
     */
    public function drainCancelled(array $records): array;

    /**
     * @param list<TaskRecordInterface> $records
     */
    public function cancelRemaining(array $records, int $deadlineNanoseconds): void;
}
