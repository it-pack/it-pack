<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use Swow\Event;
use Throwable;

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
    use StreamSelectPollerTrait;

    protected function getHandleIdPrefix(): string
    {
        return 'swow-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !class_exists('Swow\\Event')) {
            return;
        }

        // Swow's reactor only accepts real socket/stream descriptors; for
        // non-selectable resources (e.g. php://memory) Event::add returns false
        // and emits a warning, which we suppress and ignore — readiness is still
        // probed via stream_select in await().
        $registered = @Event::add($stream, null, null, Event::TYPE_READ);

        if ($registered === true) {
            $this->registered[$id] = $stream;
        }
    }

    protected function releaseFromReactor(mixed $callbackId): void
    {
        $stream = $callbackId;

        if (!is_resource($stream)) {
            return;
        }

        try {
            Event::del($stream);
        } catch (Throwable) {
            // Reactor may not be running; bookkeeping is the source of truth.
        }
    }
}
