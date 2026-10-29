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
     */
    public const READABLE = 1;
}
