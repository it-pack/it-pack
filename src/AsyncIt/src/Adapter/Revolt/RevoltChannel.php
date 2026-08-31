<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

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
  * @api

  */
final class RevoltChannel implements TaskChannelInterface
{
    public function __construct(private readonly EmulatedChannel $channel)
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

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
