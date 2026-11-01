<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Owns the scheduler's monotonic timer bookkeeping.
 *
 * Extracted from {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * during the Phase 4 SRP split. Timers are kept in a deadline-sorted array so
 * {@see nearestDeadline()} is O(1) and {@see drainExpired()} is O(k) for k
 * expired timers. No `new` construction is used, keeping the service free of
 * direct instantiation outside factories.
 *
 * @see https://w3id.org/itpack/asyncit#Timer
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
final class TaskTimerService implements TaskTimerServiceInterface
{
    /**
     * @var list<array{deadline: int, record: TaskRecordInterface, type: string|null, taskId: string}>
     *
     * Sorted by `deadline` ascending.
     */
    private array $timers = [];

    public function __construct(
        private readonly MonotonicClockInterface $clock,
    ) {
    }

    public function scheduleTimer(
        TaskRecordInterface $record,
        DurationInterface $duration,
        ?string $taskId = null,
        ?string $type = null,
    ): void {
        $now = $this->clock->monotonicNanoseconds();
        $deadline = $now + $duration->nanoseconds();
        $taskId ??= $record->task()->id()->value;

        $entry = [
            'deadline' => $deadline,
            'record' => $record,
            'type' => $type,
            'taskId' => $taskId,
        ];

        $count = \count($this->timers);
        $lo = 0;
        $hi = $count;

        while ($lo < $hi) {
            $mid = (int) (($lo + $hi) / 2);

            if ($this->timers[$mid]['deadline'] > $deadline) {
                $hi = $mid;
                continue;
            }

            $lo = $mid + 1;
        }

        array_splice($this->timers, $lo, 0, [$entry]);
    }

    /**
     * Returns every timer whose deadline has elapsed, then drops them from the
     * internal list.
     *
     * @return list<array{record: TaskRecordInterface, type: string|null, taskId: string}>
     */
    public function drainExpired(int $now): array
    {
        $expired = [];
        $count = \count($this->timers);
        $i = 0;

        while ($i < $count && $this->timers[$i]['deadline'] <= $now) {
            $expired[] = $this->timers[$i];
            $i++;
        }

        if ($i > 0) {
            array_splice($this->timers, 0, $i);
        }

        return $expired;
    }

    public function nearestDeadline(): int
    {
        return $this->timers[0]['deadline'] ?? PHP_INT_MAX;
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
