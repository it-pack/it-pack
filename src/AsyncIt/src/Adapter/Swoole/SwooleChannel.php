<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Channel\ChannelBehaviorTrait;
use Swoole\Coroutine\Channel;

/**
 * Native {@see TaskChannelInterface} over `Swoole\Coroutine\Channel`.
 *
 * Selected only when the negotiated {@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * capability is {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported}.
 * The underlying channel is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types.
 *
 * Closed-state tracking, `close()`, and `waitForCapacity()` are shared via
 * {@see ChannelBehaviorTrait}; only `send()`/`trySend()`/`receive()` remain
 * adapter-specific (Swoole's `push()` does not fail, Swow's can).
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 * @api
 *
 */
final class SwooleChannel implements TaskChannelInterface
{
    use ChannelBehaviorTrait;

    public function __construct(private readonly Channel $channel)
    {
    }

    public function send(mixed $value): void
    {
        $this->requireOpen();

        $this->channel->push($value);
    }

    public function receive(): mixed
    {
        return $this->channel->pop();
    }

    public function trySend(mixed $value): bool
    {
        $this->requireOpen();

        if ($this->channel->isFull()) {
            return false;
        }

        $this->channel->push($value);

        return true;
    }
}
