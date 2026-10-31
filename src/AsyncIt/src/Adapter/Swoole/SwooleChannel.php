<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use Swoole\Coroutine\Channel;

/**
 * Internal sentinel object used by {@see SwooleChannel::waitForCapacity()}
 * to mark a blocking push, compared by identity.
 */
final class CapacitySentinel
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}

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
 *   @api

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

        if (!$this->channel->isFull()) {
            return;
        }

        $sentinel = CapacitySentinel::instance();
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
