<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\SchedulerConfiguration;

/**
 * Orchestrates graceful shutdown of the scheduler.
 *
 * On signal (SIGTERM/SIGINT, CLI-only), it triggers cancellation on the root
 * {@see CancellationScopeInterface} which cascades to all child tasks
 * (structured-concurrency cancellation, V.1), then signals the reactor to drain
 * within the configured {@see SchedulerConfiguration::gracefulShutdownDeadline}.
 * Poll handle cleanup on terminal transition is already handled by
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
    ) {
    }

    /**
     * Initiates graceful shutdown: cancels the root scope (cascading), then lets
     * the reactor drain in-flight tasks until idle. The shutdown deadline is
     * advisory — tasks unwind cooperatively at their await boundaries. The
     * configured deadline from {@see SchedulerConfiguration::gracefulShutdownDeadline}
     * bounds the reactor drain.
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

        $this->scheduler->run();
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
