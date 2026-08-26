<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Interop\Amp;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;

/**
 * Amp interop {@see PollerPortInterface} bridge.
 *
 * Lets an existing Amp event loop host AsyncIt tasks: readiness watchers are
 * registered on `Amp\Loop` (the Amp event-loop facade) when the descriptor is
 * watchable, so AsyncIt joins the host loop. AsyncIt's scheduler still drives
 * suspension through PHP Fibers and probes readiness with `await(0)`, so this
 * bridge also performs a portable `stream_select()` readiness check inside
 * `await()` — the same model as the Swoole/Revolt pollers. Every registered
 * handle is tracked so conformance Assertion C observes deterministic cleanup.
 *
 * This is interop, not unification: AsyncIt's scheduling semantics are unchanged
 * and no Amp type appears in the production port surface. `Amp\Loop` is reached
 * through a guarded, reflection-free facade reference so the bridge has no hard
 * dependency on the host library and keeps the package analysable where Amp is
 * absent.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class AmpPollerBridge implements PollerPortInterface
{
    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, string> amp watcher ids keyed by handle id
     */
    private array $ampIds = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandle
    {
        $id = 'amp-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithLoop($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandle $handle): void
    {
        $id = $handle->id;

        if (isset($this->ampIds[$id])) {
            $this->cancelOnLoop($this->ampIds[$id]);
            unset($this->ampIds[$id]);
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

    private function registerWithLoop(string $id, mixed $stream): void
    {
        $amp = 'Amp\\Loop';

        if (!is_resource($stream) || !class_exists($amp) || !method_exists($amp, 'onReadable')) {
            return;
        }

        /** @var callable $onReadable */
        $onReadable = $amp . '::onReadable';
        $watcherId = ($onReadable)($stream, static fn (): null => null);

        if (is_string($watcherId) && $watcherId !== '') {
            $this->ampIds[$id] = $watcherId;
        }
    }

    private function cancelOnLoop(string $watcherId): void
    {
        $amp = 'Amp\\Loop';

        if (!class_exists($amp) || !method_exists($amp, 'cancel')) {
            return;
        }

        /** @var callable $cancel */
        $cancel = $amp . '::cancel';
        ($cancel)($watcherId);
    }
}
