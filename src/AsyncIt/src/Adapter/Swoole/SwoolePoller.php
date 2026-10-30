<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;

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

    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, true> ids registered with the Swoole reactor
     */
    private array $swooleRegistered = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandle
    {
        $id = 'swoole-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithReactor($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandle $handle): void
    {
        $id = $handle->id;

        if (isset($this->swooleRegistered[$id])) {
            $stream = $this->handles[$id] ?? null;
            if (is_resource($stream)) {
                @\Swoole\Event::del($stream);
            }
            unset($this->swooleRegistered[$id]);
        }

        unset($this->handles[$id]);
    }

    public function await(int $timeoutNanoseconds): void
    {
        $this->probeReadiness($this->handles, $timeoutNanoseconds);
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }

    private function registerWithReactor(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !class_exists('Swoole\\Event')) {
            return;
        }

        // Swoole's reactor only accepts real socket/stream descriptors; for
        // non-selectable resources (e.g. php://memory) Event::add returns false
        // and emits a warning, which we suppress and ignore — readiness is still
        // probed via stream_select in await().
        $registered = @\Swoole\Event::add($stream, null, null, SWOOLE_EVENT_READ);

        if ($registered === true) {
            $this->swooleRegistered[$id] = true;
        }
    }
}
