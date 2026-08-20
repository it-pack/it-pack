<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use Throwable;

/**
 * Reports persistent-worker lifecycle events as RunIt artifacts.
 *
 * A long-running worker has its own lifecycle distinct from per-request or per-job
 * executions. Implementations record start, stop, and failure as occurrences owned by a
 * dedicated worker-lifecycle execution, and expose a reset hook so worker-local state is
 * released on reload.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface WorkerLifecycleInterface
{
    public function start(): void;

    public function stop(): void;

    public function failure(Throwable $throwable): void;

    /**
     * Releases worker-local state and flushes publishers within the supplied budget.
     */
    public function reset(): void;
}
