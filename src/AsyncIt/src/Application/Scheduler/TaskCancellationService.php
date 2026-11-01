<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\Port\CancellationReason;

/**
 * Owns cancellation scope management for the reactor.
 *
 * Extracted from {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * during the Phase 4 SRP split. Identifies cancelled records and force-cancels
 * remaining records on deadline expiry.
 *
 * @see https://w3id.org/asyncit#CancellationScope
 * @see https://w3id.org/asyncit#Scheduler
 * @api
 */
final class TaskCancellationService implements TaskCancellationServiceInterface
{
    /**
     * Returns records whose cancellation scope is cancelled and which are not
     * yet done.
     *
     * @param list<TaskRecordInterface> $records
     * @return list<TaskRecordInterface>
     */
    public function drainCancelled(array $records): array
    {
        $cancelled = [];

        foreach ($records as $record) {
            if (!$record->isDone() && $record->task()->cancellationScope()->isCancelled()) {
                $cancelled[] = $record;
            }
        }

        return $cancelled;
    }

    /**
     * @param list<TaskRecordInterface> $records
     */
    public function cancelRemaining(array $records, int $deadlineNanoseconds): void
    {
        foreach ($records as $record) {
            $record->task()->cancellationScope()->cancel(
                CancellationReason::DeadlineExceeded,
            );
        }
    }
}
