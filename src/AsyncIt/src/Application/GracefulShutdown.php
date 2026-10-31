<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Orchestrates graceful shutdown of the scheduler.
 *
 * On signal (SIGTERM/SIGINT, CLI-only) or explicit call, it triggers
 * cancellation on the root {@see CancellationScopeInterface} which cascades to
 * all child tasks (structured-concurrency cancellation, V.1), then drains the
 * reactor within the configured {@see DurationInterface} deadline. Poll handle
 * cleanup on terminal transition is already handled by
 * {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler} (Assertion C).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see \ItPack\AsyncIt\Contract\Port\CancellationReason::SystemShutdown
 */
final class GracefulShutdown
{
    private bool $initiated = false;

    public function __construct(
        private readonly EngineSchedulerInterface $scheduler,
        private readonly CancellationScopeInterface $rootScope,
        private readonly MonotonicClockInterface $clock,
        private readonly DurationInterface $deadline,
    ) {
    }

    /**
     * Initiates graceful shutdown: cancels the root scope (cascading), then lets
     * the reactor drain in-flight tasks until idle or the deadline elapses.
     *
     * The deadline is enforced at the scheduler level — after it expires,
     * remaining tasks are force-cancelled with
     * {@see CancellationReason::DeadlineExceeded} and their handles released.
     * Tasks unwind cooperatively at their await boundaries before the deadline.
     */
    public function shutdown(): void
    {
        if ($this->initiated) {
            return;
        }

        $this->initiated = true;

        $this->rootScope->cancel(
            CancellationReason::SystemShutdown,
        );

        $deadlineNanos = $this->clock->monotonicNanoseconds()
            + $this->deadline->nanoseconds();

        $this->scheduler->runUntil($deadlineNanos);
    }

    /**
     * Registers signal handlers for SIGTERM and SIGINT (CLI-only).
     *
     * Non-CLI environments (php-fpm, workers, etc.) must call
     * {@see self::shutdown()} explicitly.
     */
    public function registerSignals(): void
    {
        if (PHP_SAPI !== 'cli' || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, $this->handleSignal(...));
        pcntl_signal(SIGINT, $this->handleSignal(...));
    }

    private function handleSignal(int $signal): void
    {
        $this->shutdown();
    }
}
