<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Contract\TaskChannelInterface;

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
 */
final class SwowChannel implements TaskChannelInterface
{
    private bool $closed = false;

    public function __construct(private readonly object $channel)
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
