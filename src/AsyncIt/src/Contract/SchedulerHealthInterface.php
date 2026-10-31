<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Optional health-reporting port for the scheduler.
 *
 * Adapters and tests query this interface to verify runtime integrity: no leaked
 * poll handles, no deadlock (runnable queue empty + suspended tasks + no timers
 * due), no starvation (no progress over a sustained period), and no recent
 * errors. The health contract is intentionally read-only and snapshot-based.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 */
interface SchedulerHealthInterface
{
    /**
     * Whether the scheduler is healthy — no leaked handles, no deadlock,
     * no starvation detected, and no critical errors recorded.
     */
    public function isHealthy(): bool;

    /**
     * Number of tasks currently waiting to run.
     */
    public function pendingTaskCount(): int;

    /**
     * Number of poll handles currently registered with the poller.
     */
    public function activeHandleCount(): int;

    /**
     * Wall duration the scheduler has been active, measured by the injected
     * monotonic clock.
     */
    public function uptime(): DurationInterface;

    /**
     * Returns the most recent N errors encountered by the scheduler.
     *
     * @return list<Throwable>
     */
    public function recentErrors(int $n): array;
}
