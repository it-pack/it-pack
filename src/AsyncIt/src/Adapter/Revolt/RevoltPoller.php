<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use ItPack\AsyncIt\Domain\StreamPollHandle;
use Revolt\EventLoop;
use Throwable;

/**
 * Revolt-backed {@see PollerPortInterface}.
 *
 * Readiness watchers are registered on the Revolt event loop via
 * {@see \Revolt\EventLoop::onReadable()} when the descriptor is watchable, so
 * the adapter joins the runtime's loop. AsyncIt's scheduler drives suspension
 * through PHP Fibers and probes readiness with `await(0)`, so the poller also
 * performs a portable `stream_select()` readiness check inside
 * {@see self::await()} — the same model as the reference `SelectPoller`. Every
 * registered handle is tracked so conformance Assertion C can observe
 * deterministic cleanup.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 */
final class RevoltPoller implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    public function register(mixed $stream): StreamPollHandleInterface
    {
        $id = 'revolt-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithReactor($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandleInterface $handle): void
    {
        $id = $handle->id;

        if (isset($this->registered[$id])) {
            $this->releaseFromReactor($this->registered[$id]);
            unset($this->registered[$id]);
        }

        unset($this->handles[$id]);
    }

    private function registerWithReactor(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !class_exists(EventLoop::class)) {
            return;
        }

        // Revolt's loop only accepts real descriptors; for non-selectable
        // resources (e.g. php://memory) registration returns null/false, which
        // we ignore — readiness is still probed via stream_select in await().
        $callbackId = @EventLoop::onReadable($stream, static fn (): null => null);

        if ($callbackId !== '') {
            $this->registered[$id] = $callbackId;
        }
    }

    private function releaseFromReactor(mixed $callbackId): void
    {
        if (!is_string($callbackId)) {
            return;
        }

        try {
            EventLoop::cancel($callbackId);
        } catch (Throwable) {
            // Loop may not be running; bookkeeping below is the source of truth.
        }
    }
}
