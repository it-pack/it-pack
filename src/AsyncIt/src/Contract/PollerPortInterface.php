<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;

// Remove this import; keep the fully-qualified class name in the @see docblock.

/**
 * The polling contract — an OS-readiness notifier, not a scheduler.
 *
 * `Io\Poll` (PHP 8.6) and the `SelectPoller` fallback both implement this port.
 * The poller only signals resource readiness; it must never manage tasks,
 * timers, or Fiber lifecycles. Every {@see StreamPollHandle} it returns must be
 * released on terminal transition (conformance Assertion C).
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
interface PollerPortInterface
{
    /**
     * Registers a stream for readiness and returns its per-task watcher handle.
     *
     * @param mixed $stream a stream resource or socket accepted by the backend
     */
    public function register(mixed $stream): StreamPollHandleInterface;

    /**
     * Releases a previously registered handle, detaching any OS-level watcher.
     */
    public function release(StreamPollHandleInterface $handle): void;

    /**
     * Blocks until at least one registered stream is ready or the monotonic
     * timeout (nanoseconds) elapses.
     */
    public function await(int $timeoutNanoseconds): void;

    /**
     * Number of handles currently registered with the poller.
     *
     * Used by conformance Assertion C to prove every {@see StreamPollHandle} is
     * released on terminal transition (no dangling watchers).
     *
     * @see https://w3id.org/itpack/asyncit#StreamPollHandle
     */
    public function activeHandleCount(): int;
}
