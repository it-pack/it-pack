<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;

/**
 * Swow-backed {@see PollerPortInterface}.
 *
 * Readiness watchers are registered on the Swow reactor via
 * {@see \Swow\Event::add()} when the descriptor is watchable, so the adapter
 * joins the runtime's loop. AsyncIt's scheduler drives suspension through PHP
 * Fibers and probes readiness with `await(0)`, so the poller also performs a
 * portable `stream_select()` readiness check inside {@see self::await()} — the
 * same model as the reference `SelectPoller`. Every registered handle is
 * tracked so conformance Assertion C can observe deterministic cleanup.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 */
final class SwowPoller implements PollerPortInterface
{
    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, true> ids registered with the Swow reactor
     */
    private array $swowRegistered = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandle
    {
        $id = 'swow-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithReactor($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandle $handle): void
    {
        $id = $handle->id;

        if (isset($this->swowRegistered[$id])) {
            $stream = $this->handles[$id] ?? null;
            if (is_resource($stream)) {
                try {
                    \Swow\Event::del($stream);
                } catch (\Throwable) {
                    // Reactor may not be running; bookkeeping is the source of truth.
                }
            }
            unset($this->swowRegistered[$id]);
        }

        unset($this->handles[$id]);
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
        } catch (\ValueError) {
            // No selectable descriptors; the scheduler re-probes on the next tick.
        }
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }

    private function registerWithReactor(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !class_exists('Swow\\Event')) {
            return;
        }

        // Swow's reactor only accepts real socket/stream descriptors; for
        // non-selectable resources (e.g. php://memory) Event::add returns false
        // and emits a warning, which we suppress and ignore — readiness is still
        // probed via stream_select in await().
        $registered = @\Swow\Event::add($stream, null, null, \Swow\Event::TYPE_READ);

        if ($registered === true) {
            $this->swowRegistered[$id] = true;
        }
    }
}
