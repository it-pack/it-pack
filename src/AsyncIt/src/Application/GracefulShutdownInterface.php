<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application;

/**
 * Orchestrates graceful shutdown of the scheduler.
 *
 * Backs {@see GracefulShutdown} so shutdown orchestration remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @api
 */
interface GracefulShutdownInterface
{
    /**
     * Initiates graceful shutdown: cancels the root scope (cascading), then lets
     * the reactor drain in-flight tasks until idle or the deadline elapses.
     */
    public function shutdown(): void;

    /**
     * Registers signal handlers for SIGTERM and SIGINT (CLI-only).
     *
     * Non-CLI environments must call {@see self::shutdown()} explicitly.
     */
    public function registerSignals(): void;
}
