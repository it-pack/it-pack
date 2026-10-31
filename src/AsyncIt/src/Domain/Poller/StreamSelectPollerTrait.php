<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Poller;

use ItPack\AsyncIt\Contract\Port\PollerVocabulary;
use ValueError;

/**
 * Shared readiness probe for stream-based pollers.
 *
 * Provides a stateless, portable {@see stream_select()} implementation used by
 * every poller whose await() reduces to a stream-select readiness check.
 * Callers pass their handle collection so the trait holds no state and
 * remains safe across all adapter runtimes (Revolt, Swoole, Swow, Amp,
 * ReactPHP) and the reference SelectPoller.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 */
trait StreamSelectPollerTrait
{
    /**
     * Blocks until at least one handle is ready for reading or the monotonic
     * timeout (nanoseconds) elapses.
     *
     * Uses {@see PollerVocabulary::NANOSECONDS_PER_SECOND} and
     * {@see PollerVocabulary::NANOSECONDS_PER_MICROSECOND} for the conversion
     * so no magic numbers appear in consuming pollers.
     *
     * @param array<string, mixed> $handles id => stream resources registered
     */
    final protected function probeReadiness(array $handles, int $timeoutNanoseconds): void
    {
        $read = [];

        foreach ($handles as $stream) {
            if (is_resource($stream)) {
                $read[] = $stream;
            }
        }

        if ($read === []) {
            return;
        }

        $seconds = intdiv($timeoutNanoseconds, PollerVocabulary::NANOSECONDS_PER_SECOND);
        $microseconds = intdiv(
            $timeoutNanoseconds % PollerVocabulary::NANOSECONDS_PER_SECOND,
            PollerVocabulary::NANOSECONDS_PER_MICROSECOND
        );

        $write = [];
        $except = [];

        try {
            @stream_select($read, $write, $except, $seconds, $microseconds);
        } catch (ValueError) {
            // No selectable descriptors (e.g. in-memory test streams); the
            // scheduler re-probes on the next tick.
        }
    }
}
