<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Scheduler capable of deadline-bounded reactor draining.
 *
 * A deadline scheduler can run the reactor until a monotonic deadline elapses,
 * force-cancelling any remaining tasks when the deadline expires. This is an
 * optional capability: third-party schedulers implement it only when they
 * support bounded draining (e.g. for graceful shutdown). It is deliberately
 * *not* part of {@see EngineSchedulerInterface} so adding it does not force
 * every downstream implementation to change.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-cancellation-deadline-exceeded
 * @see \ItPack\AsyncIt\Contract\Port\CancellationReason::DeadlineExceeded
 * @api
 */
interface DeadlineSchedulerInterface extends SchedulerInterface
{
    /**
     * Runs the reactor to completion, draining the ready queue and poll
     * handles until the monotonic deadline (in nanoseconds) elapses.
     *
     * When the deadline expires, remaining tasks are force-cancelled.
     *
     * @param int $deadlineNanoseconds Monotonic deadline in nanoseconds.
     */
    public function runUntil(int $deadlineNanoseconds): void;
}
