<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Handle to the eventual result of an asynchronous operation.
 *
 * A Promise represents a value that may not yet be available — either because
 * the task is still running locally, or because it was submitted to a remote
 * scheduler via {@see \ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface}
 * and the result has not yet returned.
 *
 * Consumers poll {@see self::isDone()} or block on {@see self::wait()}.
 * {@see self::get()} returns the value once done, or throws the terminal
 * exception if the operation failed.
 *
 * @see https://w3id.org/itpack/asyncit#Promise
 * @api
 */
interface PromiseInterface
{
    /**
     * Returns true when the operation has reached a terminal state (success or
     * failure). False means the result is not yet available.
     */
    public function isDone(): bool;

    /**
     * Blocks until the operation completes or the optional monotonic timeout
     * elapses. Returns true if the result is available, false if the timeout
     * elapses first.
     *
     * Implementations inject {@see \ItPack\RunIt\Contract\Port\MonotonicClockInterface}
     * through their constructor so durations are free of wall-clock skew.
     */
    public function wait(?DurationInterface $timeout = null): bool;

    /**
     * Returns the operation's result once done.
     *
     * @throws Throwable if the operation failed; the original exception is
     *         rethrown without wrapping.
     */
    public function get(): mixed;
}
