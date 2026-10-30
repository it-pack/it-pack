<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application;

use ItPack\AsyncIt\Application\Scheduler\TaskRecord;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Throwable;

/**
 * Read-only health snapshot of the scheduler's runtime state.
 *
 * Reports pending task count, active poll handle count, uptime, and recent
 * errors. Used by {@see \ItPack\AsyncIt\SDK\AsyncRuntime} to satisfy
 * {@see SchedulerHealthInterface}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 */
final readonly class SchedulerHealth implements SchedulerHealthInterface
{
    /**
     * @param list<TaskRecord> $records
     * @param list<Throwable> $recentErrors
     */
    public function __construct(
        private array $records,
        private int $activeHandleCount,
        private MonotonicClockInterface $clock,
        private int $startTimeNanos,
        private array $recentErrors,
    ) {
    }

    /**
     * @param list<TaskRecord> $records
     * @param list<Throwable> $recentErrors
     */
    public static function capture(
        array $records,
        int $activeHandleCount,
        MonotonicClockInterface $clock,
        int $startTimeNanos,
        array $recentErrors,
    ): self {
        return new self($records, $activeHandleCount, $clock, $startTimeNanos, $recentErrors);
    }

    public function isHealthy(): bool
    {
        return $this->activeHandleCount === 0 && $this->recentErrors === [];
    }

    public function pendingTaskCount(): int
    {
        $pending = 0;

        foreach ($this->records as $record) {
            if (!$record->isDone()) {
                $pending++;
            }
        }

        return $pending;
    }

    public function activeHandleCount(): int
    {
        return $this->activeHandleCount;
    }

    public function uptime(): DurationInterface
    {
        return Duration::ofNanoseconds(
            $this->clock->monotonicNanoseconds() - $this->startTimeNanos,
        );
    }

    /**
     * @return list<Throwable>
     */
    public function recentErrors(int $n): array
    {
        return array_slice($this->recentErrors, -$n);
    }
}
