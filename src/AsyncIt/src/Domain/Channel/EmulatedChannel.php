<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Channel;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Exception\ChannelFullException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;

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
     * @var array<int, mixed|null>
     */
    private array $queue = [];

    private int $head = 0;

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

        $size = count($this->queue);
        if ($this->capacity !== null && $size >= $this->capacity) {
            throw new ChannelFullException();
        }

        $this->queue[] = $value;
    }

    public function receive(): mixed
    {
        if ($this->head >= count($this->queue)) {
            throw new ChannelEmptyException();
        }

        $value = $this->queue[$this->head];
        $this->queue[$this->head] = null;
        $this->head++;

        // Periodic compaction to bound memory: reindex when the head has
        // advanced past half the allocated slots.
        if ($this->head > 0 && ($this->head << 1) >= count($this->queue)) {
            $this->queue = array_slice($this->queue, $this->head);
            $this->head = 0;
        }

        return $value;
    }

    public function trySend(mixed $value): bool
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }

        $size = count($this->queue) - $this->head;
        if ($this->capacity !== null && $size >= $this->capacity) {
            return false;
        }

        $this->queue[] = $value;

        return true;
    }

    /**
     * Returns immediately. For bounded emulated channels, a full buffer means
     * no capacity is available; the caller should handle {@see ChannelFullException}
     * from {@see send()} for backpressure rather than relying on this method to block.
     *
     * The emulated channel has no event-loop integration, so it cannot suspend
     * the calling task — native backends (Swoole/Swow) provide true blocking
     * semantics under the same contract.
     *
     * @throws ChannelClosedException when closed
     */
    public function waitForCapacity(): void
    {
        if ($this->closed) {
            throw new ChannelClosedException();
        }
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
