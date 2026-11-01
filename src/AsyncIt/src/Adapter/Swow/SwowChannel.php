<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Contract\Exception\ChannelException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Channel\ChannelBehaviorTrait;
use Swow\Coroutine\Channel;

/**
 * Native {@see TaskChannelInterface} over `Swow\Coroutine\Channel`.
 *
 * Selected only when the negotiated {@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * capability is {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported}.
 * The underlying channel is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types.
 *
 * Closed-state tracking, `close()`, and `waitForCapacity()` are shared via
 * {@see ChannelBehaviorTrait}; only `send()`/`trySend()`/`receive()` remain
 * adapter-specific (Swow's `push()` can fail and throws `ChannelException`).
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 * @api
 *
 */
final class SwowChannel implements TaskChannelInterface
{
    use ChannelBehaviorTrait;

    public function __construct(private readonly Channel $channel)
    {
    }

    public function send(mixed $value): void
    {
        $this->requireOpen();

        if (!$this->channel->push($value)) {
            throw new ChannelException(
                'Could not send value to channel.',
            );
        }
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

        return $this->channel->push($value);
    }
}
