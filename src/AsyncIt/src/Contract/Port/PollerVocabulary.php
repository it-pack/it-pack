<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for poller readiness events.
 *
 * These mirror the readiness event types used by the underlying poll API
 * (`Io\Poll`, `stream_select`) so that adapters reference a named constant
 * instead of a magic integer.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
interface PollerVocabulary
{
    /**
     * Stream is ready for reading (maps to `Io\Poll::READABLE` / `1`).
     *
     * @see https://w3id.org/itpack/asyncit#Poller
     */
    public const READABLE = 1;

    /**
     * Nanoseconds per second, for timeout conversion in {@see PollerPortInterface::await()}.
     */
    public const NANOSECONDS_PER_SECOND = 1_000_000_000;

    /**
     * Nanoseconds per millisecond, for timeout conversion in {@see PollerPortInterface::await()}.
     */
    public const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    /**
     * Nanoseconds per microsecond, for timeout conversion in {@see PollerPortInterface::await()}.
     */
    public const NANOSECONDS_PER_MICROSECOND = 1_000;
}
