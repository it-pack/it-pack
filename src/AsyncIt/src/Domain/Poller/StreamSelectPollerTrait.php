<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Poller;

use ItPack\AsyncIt\Contract\Port\PollerVocabulary;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\StreamPollHandle;
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
     * Blocks until at least one handle is ready for reading or writing, or the
     * monotonic timeout (nanoseconds) elapses.
     *
     * Uses {@see PollerVocabulary::NANOSECONDS_PER_SECOND} and
     * {@see PollerVocabulary::NANOSECONDS_PER_MICROSECOND} for the conversion
     * so no magic numbers appear in consuming pollers.
     *
     * @param array<string, mixed> $handles id => stream resources registered
     * @return array{read: list<mixed>, write: list<mixed>} ready streams per
     *         readiness kind
     *
     * @see https://w3id.org/itpack/asyncit#Poller
     */
    final protected function probeReadiness(array $handles, int $timeoutNanoseconds): array
    {
        $read = [];
        $write = [];

        foreach ($handles as $stream) {
            if (is_resource($stream)) {
                $read[] = $stream;
                $write[] = $stream;
            }
        }

        if ($read === [] && $write === []) {
            return ['read' => [], 'write' => []];
        }

        $seconds = intdiv($timeoutNanoseconds, PollerVocabulary::NANOSECONDS_PER_SECOND);
        $microseconds = intdiv(
            $timeoutNanoseconds % PollerVocabulary::NANOSECONDS_PER_SECOND,
            PollerVocabulary::NANOSECONDS_PER_MICROSECOND
        );

        $except = [];

        try {
            @stream_select($read, $write, $except, $seconds, $microseconds);
        } catch (ValueError) {
            // No selectable descriptors (e.g. in-memory test streams); the
            // scheduler re-probes on the next tick.
            return ['read' => [], 'write' => []];
        }

        return ['read' => $read, 'write' => $write];
    }

    /**
     * Matches registered handles against readiness arrays produced by
     * {@see probeReadiness()} and returns the handles that are ready for
     * reading or writing. Clears neither array — callers manage array state.
     *
     * @param array<string, mixed> $handles id => stream resources registered
     * @param list<mixed>          $readStreams streams ready for reading
     * @param list<mixed>          $writeStreams streams ready for writing
     * @return list<StreamPollHandleInterface> handles that became ready
     *
     * @see https://w3id.org/itpack/asyncit#StreamPollHandle
     */
    final protected function matchReadyHandles(
        array $handles,
        array $readStreams,
        array $writeStreams,
    ): array {
        $ready = [];

        foreach ($handles as $id => $stream) {
            if (!is_resource($stream)) {
                continue;
            }

            $isReadReady = in_array($stream, $readStreams, strict: true);
            $isWriteReady = in_array($stream, $writeStreams, strict: true);

            if ($isReadReady || $isWriteReady) {
                $ready[] = new StreamPollHandle($id);
            }
        }

        return $ready;
    }
}
