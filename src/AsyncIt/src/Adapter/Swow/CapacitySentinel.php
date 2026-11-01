<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

/**
 * Internal sentinel object used by {@see SwowChannel::waitForCapacity()}
 * to mark a blocking push, compared by identity.
 *
 * A fresh instance is created per call to avoid cross-coroutine contamination
 * when multiple coroutines call waitForCapacity() concurrently on the same
 * channel.
 */
final class CapacitySentinel
{
    public static function create(): self
    {
        return new self();
    }
}
