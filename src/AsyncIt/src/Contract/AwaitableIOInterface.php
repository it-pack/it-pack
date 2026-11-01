<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use LogicException;

/**
 * Scheduler extension for IO await primitives.
 *
 * Adds {@see self::awaitReadable()} and {@see self::awaitWritable()} to the
 * {@see EngineSchedulerInterface} without breaking third-party implementors
 * that only implement `EngineSchedulerInterface`. Schedulers that support IO
 * await implement this interface instead of (or in addition to) the base
 * engine contract.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-readable
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-writable
 * @api
 */
interface AwaitableIOInterface extends EngineSchedulerInterface
{
    /**
     * Suspends the current task until the handle's stream is ready for reading,
     * or the optional monotonic timeout elapses.
     *
     * Returns true when the stream is readable, false when the timeout expired.
     * The handle MUST have been registered with the poller (via
     * {@see \ItPack\AsyncIt\Contract\PollerPortInterface::register()}) before
     * calling this method. The scheduler releases the handle on terminal
     * transition, timeout, or cancellation (conformance Assertion C).
     *
     * @throws LogicException when called outside a running task
     *
     * @see https://w3id.org/itpack/asyncit#StreamPollHandle
     * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-readable
     */
    public function awaitReadable(StreamPollHandleInterface $handle, ?DurationInterface $timeout = null): bool;

    /**
     * Suspends the current task until the handle's stream is ready for writing,
     * or the optional monotonic timeout elapses.
     *
     * Returns true when the stream is writable, false when the timeout expired.
     * The handle MUST have been registered with the poller (via
     * {@see \ItPack\AsyncIt\Contract\PollerPortInterface::register()}) before
     * calling this method. The scheduler releases the handle on terminal
     * transition, timeout, or cancellation (conformance Assertion C).
     *
     * @throws LogicException when called outside a running task
     *
     * @see https://w3id.org/itpack/asyncit#StreamPollHandle
     * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-writable
     */
    public function awaitWritable(StreamPollHandleInterface $handle, ?DurationInterface $timeout = null): bool;
}
