<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Interop\ReactPhp;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use ItPack\AsyncIt\Domain\StreamPollHandle;

/**
 * ReactPHP interop {@see PollerPortInterface} bridge.
 *
 * Lets an existing ReactPHP event loop host AsyncIt tasks: readiness watchers
 * are registered on the supplied `React\EventLoop\LoopInterface` when the
 * descriptor is watchable, so AsyncIt joins the host loop. AsyncIt's scheduler
 * still drives suspension through PHP Fibers and probes readiness with
 * `await(0)`, so this bridge also performs a portable `stream_select()`
 * readiness check inside `await()` — the same model as the Swoole/Revolt
 * pollers. Every registered handle is tracked so conformance Assertion C
 * observes deterministic cleanup.
 *
 * This is interop, not unification: AsyncIt's scheduling semantics are unchanged
 * and no ReactPHP type appears in the production port surface. The loop is
 * injected (an `object` standing in for `React\EventLoop\LoopInterface`) so the
 * bridge has no hard dependency on the host library and keeps the package
 * analysable where ReactPHP is absent.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class ReactPhpPollerBridge implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, true> ids registered with the ReactPHP loop
     */
    private array $reactRegistered = [];

    /**
     * @var list<mixed> streams that became readable during the last await() call
     */
    private array $readyReadStreams = [];

    /**
     * @var list<mixed> streams that became writable during the last await() call
     */
    private array $readyWriteStreams = [];

    private int $counter = 0;

    public function __construct(private readonly object $loop)
    {
    }

    public function register(mixed $stream): StreamPollHandleInterface
    {
        $id = 'react-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithLoop($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandleInterface $handle): void
    {
        $id = $handle->id;

        if (isset($this->reactRegistered[$id])) {
            $this->removeFromLoop($id);
            unset($this->reactRegistered[$id]);
        }

        unset($this->handles[$id]);
    }

    public function await(int $timeoutNanoseconds): void
    {
        $readiness = $this->probeReadiness($this->handles, $timeoutNanoseconds);
        $this->readyReadStreams = $readiness['read'];
        $this->readyWriteStreams = $readiness['write'];
    }

    public function readyHandles(): array
    {
        $ready = $this->matchReadyHandles(
            $this->handles,
            $this->readyReadStreams,
            $this->readyWriteStreams,
        );

        $this->readyReadStreams = [];
        $this->readyWriteStreams = [];

        return $ready;
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }

    private function registerWithLoop(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !method_exists($this->loop, 'addReadStream')) {
            return;
        }

        /** @var callable $add */
        $add = [$this->loop, 'addReadStream'];
        ($add)($stream, static fn (): null => null);

        $this->reactRegistered[$id] = true;
    }

    private function removeFromLoop(string $id): void
    {
        $stream = $this->handles[$id] ?? null;

        if (!is_resource($stream) || !method_exists($this->loop, 'removeReadStream')) {
            return;
        }

        /** @var callable $remove */
        $remove = [$this->loop, 'removeReadStream'];
        ($remove)($stream);
    }
}
