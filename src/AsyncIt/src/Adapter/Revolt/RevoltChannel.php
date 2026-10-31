<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;

/**
 * Revolt {@see TaskChannelInterface}.
 *
 * Revolt has no native channel primitive, so the channel is emulated through
 * the runtime-agnostic {@see EmulatedChannel} (injected by the assembler). The
 * same interface means consumers cannot tell the backend apart.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 *   @api
 */
final readonly class RevoltChannel implements TaskChannelInterface
{
    public function __construct(private EmulatedChannel $channel)
    {
    }

    public function send(mixed $value): void
    {
        $this->channel->send($value);
    }

    public function receive(): mixed
    {
        return $this->channel->receive();
    }

    public function trySend(mixed $value): bool
    {
        if ($this->channel->isClosed()) {
            throw new ChannelClosedException();
        }

        return $this->channel->trySend($value);
    }

    /**
     * Returns immediately. Delegates to the underlying {@see EmulatedChannel}
     * which cannot block without event-loop integration.
     *
     * @throws ChannelClosedException when closed
     */
    public function waitForCapacity(): void
    {
        $this->channel->waitForCapacity();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
