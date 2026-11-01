<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use Swoole\Event;

/**
 * Swoole-backed {@see PollerPortInterface}.
 *
 * Readiness watchers are registered on the Swoole reactor via
 * {@see \Swoole\Event::add()} when the descriptor is watchable, so the adapter
 * genuinely joins the runtime's loop. Because AsyncIt's scheduler drives
 * suspension through PHP Fibers and calls `await(0)` as a readiness probe, the
 * poller also performs a portable `stream_select()` readiness check inside
 * {@see self::await()} — the same model as the reference `SelectPoller`. Every
 * registered handle is tracked so conformance Assertion C can observe
 * deterministic cleanup, and Swoole watchers are detached on {@see self::release()}.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 */
final class SwoolePoller implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    protected function getHandleIdPrefix(): string
    {
        return 'swoole-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !class_exists(Event::class)) {
            return;
        }

        // Swoole's reactor only accepts real socket/stream descriptors; for
        // non-selectable resources (e.g. php://memory) Event::add returns false
        // and emits a warning, which we suppress and ignore — readiness is still
        // probed via stream_select in await().
        $registered = @Event::add($stream, read_callback: null, events: SWOOLE_EVENT_READ);

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

        Event::del($stream);
    }
}
