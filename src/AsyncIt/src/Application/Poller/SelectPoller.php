<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Poller;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;

/**
 * Reference poller backed by `stream_select()`.
 *
 * It implements the {@see PollerPortInterface} readiness contract for the
 * reference runtime: handles are tracked until released so conformance
 * Assertion C can observe deterministic cleanup. It never pretends to be the
 * native PHP 8.6 `Io\Poll` API; it is a weaker, development-grade fallback with
 * the same behavioural contract.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class SelectPoller implements PollerPortInterface
{
    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandle
    {
        $id = 'select-' . ++$this->counter;
        $this->handles[$id] = $stream;

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandle $handle): void
    {
        unset($this->handles[$handle->id]);
    }

    public function await(int $timeoutNanoseconds): void
    {
        $read = [];
        foreach ($this->handles as $stream) {
            if (is_resource($stream)) {
                $read[] = $stream;
            }
        }

        if ($read === []) {
            return;
        }

        $seconds = intdiv($timeoutNanoseconds, 1_000_000_000);
        $microseconds = intdiv($timeoutNanoseconds % 1_000_000_000, 1000);
        $write = [];
        $except = [];

        try {
            @stream_select($read, $write, $except, $seconds, $microseconds);
        } catch (\ValueError $e) {
            // No selectable descriptors (e.g. in-memory test streams); there is
            // nothing to wait on, so the poll is a no-op. The leak check does
            // not depend on real I/O.
        }
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }
}
