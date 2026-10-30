<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use Swoole\Coroutine\Channel;

/**
 * Native {@see TaskChannelInterface} over `Swoole\Coroutine\Channel`.
 *
 * Selected only when the negotiated {@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * capability is {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported}.
 * The underlying channel is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
  * @api

  */
final class SwooleChannel implements TaskChannelInterface
{
    private bool $closed = false;

    public function __construct(private readonly Channel $channel)
    {
    }

    public function send(mixed $value): void
    {
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

        return $this->channel->push($value, 0);
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

        // For bounded channels at capacity, suspend the coroutine until a
        // consumer frees a slot. Swoole\Coroutine\Channel::push() blocks
        // natively at the coroutine level when the channel is full. We push
        // a sentinel and immediately pop it. In Swoole's cooperative scheduling
        // model there is no yield point between push() returning and pop()
        // executing, so no other coroutine can observe the sentinel. This
        // atomicity assumption holds as long as the Swoole extension
        // preserves cooperative scheduling guarantees between consecutive
        // non-blocking Channel operations.
        $this->channel->push(null);
        $this->channel->pop();
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
