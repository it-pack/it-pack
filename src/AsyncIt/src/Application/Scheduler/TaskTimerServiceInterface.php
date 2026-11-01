<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Monotonic timer bookkeeping for the scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#Timer
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
interface TaskTimerServiceInterface
{
    public function scheduleTimer(TaskRecordInterface $record, DurationInterface $duration, ?string $taskId = null, ?string $type = null): void;

    /**
     * @return list<array{record: TaskRecordInterface, type: string|null, taskId: string|null}>
     */
    public function drainExpired(int $now): array;

    public function nearestDeadline(): int;

    public function clearForRecord(TaskRecordInterface $record): void;

    public function hasTimers(): bool;
}
