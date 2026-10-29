<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

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
  * @api

  */
interface TaskChannelInterface
{
    /**
     * Sends a value into the channel.
     *
     * @throws \ItPack\AsyncIt\Contract\Exception\ChannelClosedException when closed
     * @throws \ItPack\AsyncIt\Contract\Exception\ChannelFullException when bounded and full
     */
    public function send(mixed $value): void;

    /**
     * Receives the next value, or signals emptiness for the emulated backend.
     *
     * @throws \ItPack\AsyncIt\Contract\Exception\ChannelEmptyException when no value is available
     */
    public function receive(): mixed;

    public function isClosed(): bool;

    public function close(): void;
}
