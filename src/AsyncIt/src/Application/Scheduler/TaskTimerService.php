<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Owns the scheduler's monotonic timer bookkeeping.
 *
 * Extracted from {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * during the Phase 4 SRP split. The service owns the `$timers` array and the
 * clock; the scheduler calls it to schedule, expire, and clear timers, then
 * applies the returned expired records to its own ready queue.
 *
 * @see https://w3id.org/itpack/asyncit#Timer
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
final class TaskTimerService implements TaskTimerServiceInterface
{
    /**
     * @var list<array{deadline: int, record: TaskRecordInterface, type: string|null, taskId: string|null}>
     */
    private array $timers = [];

    public function __construct(
        private readonly MonotonicClockInterface $clock,
    ) {
    }

    public function scheduleTimer(TaskRecordInterface $record, DurationInterface $duration, ?string $taskId = null, ?string $type = null): void
    {
        $now = $this->clock->monotonicNanoseconds();
        $deadline = $now + $duration->nanoseconds();

        $this->timers[] = [
            'deadline' => $deadline,
            'record' => $record,
            'type' => $type,
            'taskId' => $taskId ?? $record->task()->id()->value,
        ];
    }

    /**
     * Returns every timer whose deadline has elapsed, then drops them from the
     * internal list.
     *
     * @return list<array{record: TaskRecordInterface, type: string|null, taskId: string|null}>
     */
    public function drainExpired(int $now): array
    {
        $expired = [];
        $remaining = [];

        foreach ($this->timers as $entry) {
            if ($entry['deadline'] <= $now) {
                $expired[] = $entry;
                continue;
            }

            $remaining[] = $entry;
        }

        $this->timers = $remaining;

        return $expired;
    }

    public function nearestDeadline(): int
    {
        $nearest = PHP_INT_MAX;

        foreach ($this->timers as $entry) {
            if ($entry['deadline'] < $nearest) {
                $nearest = $entry['deadline'];
            }
        }

        return $nearest;
    }

    public function clearForRecord(TaskRecordInterface $record): void
    {
        $this->timers = array_values(
            array_filter(
                $this->timers,
                static fn (array $entry): bool => $entry['record'] !== $record,
            ),
        );
    }

    public function hasTimers(): bool
    {
        return $this->timers !== [];
    }
}
