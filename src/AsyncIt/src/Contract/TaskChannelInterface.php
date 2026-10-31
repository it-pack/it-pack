<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Exception\ChannelFullException;

/**
 * A coordination primitive for handing values between tasks.
 *
 * The same interface is backed by a native runtime channel (where the
 * negotiated {@see \ItPack\AsyncIt\Contract\Port\CapabilityKind::NativeChannels}
 * capability is {@see \ItPack\AsyncIt\Contract\Port\CapabilityStatus::Supported})
 * or an emulated userspace channel otherwise. The scheduler and consumers never
 * learn which backend is active.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-block
 * @api
 */
interface TaskChannelInterface
{
    /**
     * Sends a value into the channel.
     *
     * @throws ChannelClosedException when closed
     * @throws ChannelFullException when bounded and full
     */
    public function send(mixed $value): void;

    /**
     * Receives the next value, or signals emptiness for the emulated backend.
     *
     * @throws ChannelEmptyException when no value is available
     */
    public function receive(): mixed;

    /**
     * Attempts a non-blocking send. Returns false when the channel is full
     * (backpressure policy: drop), true on success.
     *
     * @throws ChannelClosedException when closed
     */
    public function trySend(mixed $value): bool;

    /**
     * Blocks (suspends) the calling task until buffer capacity is available
     * (backpressure policy: block) or the channel is closed.
     *
     * @throws ChannelClosedException when closed
     */
    public function waitForCapacity(): void;

    public function isClosed(): bool;

    public function close(): void;
}
