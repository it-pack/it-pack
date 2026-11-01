<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Interop\Amp;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use ItPack\AsyncIt\Domain\StreamPollHandle;

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
    use StreamSelectPollerTrait;

    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, string> amp watcher ids keyed by handle id
     */
    private array $ampIds = [];

    /**
     * @var list<mixed> streams that became readable during the last await() call
     */
    private array $readyReadStreams = [];

    /**
     * @var list<mixed> streams that became writable during the last await() call
     */
    private array $readyWriteStreams = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandleInterface
    {
        $id = 'amp-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithLoop($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandleInterface $handle): void
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
        $amp = 'Amp\\Loop';

        if (!is_resource($stream) || !class_exists($amp)) {
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

        if (!class_exists($amp)) {
            return;
        }

        /** @var callable $cancel */
        $cancel = $amp . '::cancel';
        ($cancel)($watcherId);
    }
}
