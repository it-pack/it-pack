<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Channel;

use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Exception\ChannelClosedException;
use ItPack\AsyncIt\Domain\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Domain\Exception\ChannelFullException;

/**
 * Userspace {@see TaskChannelInterface} used when the negotiated runtime has no
 * native channel support ({@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * is not {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported}).
 *
 * It is a non-blocking, bounded-unbounded mailbox: {@see self::receive()} returns
 * the oldest queued value or signals emptiness. Native backends (Swoole/Swow)
 * provide truly blocking channel semantics under the same contract.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 */
final class EmulatedChannel implements TaskChannelInterface
{
    /**
     * @var list<mixed>
     */
    private array $queue = [];

    private bool $closed = false;

    /**
     * @param int|null $capacity maximum buffered values; null = unbounded
     */
    public function __construct(private readonly ?int $capacity = null)
    {
    }

    public function send(mixed $value): void
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }

        if ($this->capacity !== null && count($this->queue) >= $this->capacity) {
            throw new ChannelFullException();
        }

        $this->queue[] = $value;
    }

    public function receive(): mixed
    {
        if ($this->queue === []) {
            throw new ChannelEmptyException();
        }

        return array_shift($this->queue);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
