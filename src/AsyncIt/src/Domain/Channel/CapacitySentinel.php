<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Channel;

/**
 * Internal sentinel object used by {@see ChannelBehaviorTrait::waitForCapacity()}
 * to mark a blocking push, compared by identity.
 *
 * A fresh instance is created per call to avoid cross-task contamination
 * when multiple tasks call waitForCapacity() concurrently on the same
 * channel. Shared by the Swoole and Swow channel adapters.
 */
final class CapacitySentinel
{
    public static function create(): self
    {
        return new self();
    }
}
