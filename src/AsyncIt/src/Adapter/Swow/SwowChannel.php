<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use Swow\Coroutine\Channel;

/**
 * Native {@see TaskChannelInterface} over `Swow\Coroutine\Channel`.
 *
 * Selected only when the negotiated {@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * capability is {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported}.
 * The underlying channel is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 * @api
 *
 */
final class SwowChannel implements TaskChannelInterface
{
    private bool $closed = false;

    public function __construct(private readonly Channel $channel)
    {
    }

    public function send(mixed $value): void
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }

        $this->channel->push($value);
    }

    public function receive(): mixed
    {
        return $this->channel->pop();
    }

    public function trySend(mixed $value): bool
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }

        if ($this->channel->isFull()) {
            return false;
        }

        $this->channel->push($value);

        return true;
    }

    public function waitForCapacity(): void
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }

        // Unbounded channels are never full — return immediately.
        if (!$this->channel->isFull()) {
            return;
        }

        // Block the coroutine until a consumer frees a slot. push() yields
        // when the channel is full, resuming once capacity is available.
        $sentinel = CapacitySentinel::create();
        $this->channel->push($sentinel);

        // Remove the sentinel to restore the channel's original contents.
        // Swow's pop() returns the FIFO front, so we drain and re-queue
        // items until the sentinel is found.
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
}
