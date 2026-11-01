<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Channel;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;

/**
 * Shared channel lifecycle: closed-state tracking, `close()`, and the
 * sentinel-based `waitForCapacity()` drain/re-queue cycle.
 *
 * Consuming adapters (`SwooleChannel`, `SwowChannel`) provide their own
 * `$this->channel` (a native `Channel`) and their own `send()`/`trySend()`
 * semantics — Swow's `push()` may fail and throw, Swoole's does not — so
 * those remain adapter-specific. Everything else is character-for-character
 * identical across both adapters.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
trait ChannelBehaviorTrait
{
    private bool $closed = false;

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;
            $this->channel->close();
        }
    }

    /**
     * Blocks (suspends) the calling task until buffer capacity is available
     * or the channel is closed.
     *
     * Pushes a fresh sentinel, drains the channel until the sentinel is
     * found, then re-queues the drained items so the channel's original
     * contents are restored.
     *
     * @throws ChannelClosedException when closed
     */
    public function waitForCapacity(): void
    {
        $this->requireOpen();

        if (!$this->channel->isFull()) {
            return;
        }

        $sentinel = CapacitySentinel::create();
        $this->channel->push($sentinel);

        $drained = [];
        while (!$this->channel->isEmpty()) {
            $item = $this->channel->pop();
            if ($item === $sentinel) {
                break;
            }

            $drained[] = $item;
        }

        foreach ($drained as $item) {
            $this->channel->push($item);
        }
    }

    /**
     * Throws {@see ChannelClosedException} when the channel is closed.
     *
     * Declares the closed-state guard once so {@see send()}, {@see trySend()},
     * and {@see waitForCapacity()} do not repeat it per adapter.
     */
    protected function requireOpen(): void
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }
    }
}
