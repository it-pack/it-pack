<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;

interface BulkheadInterface
{
    /**
     * Attempts to acquire a slot without blocking.
     *
     * @throws BulkheadFullException when at capacity
     */
    public function tryAcquire(): void;

    /**
     * Attempts to acquire a slot. Returns true if acquired, false if at
     * capacity (caller should suspend/yield and retry).
     */
    public function waitForCapacity(): bool;

    /**
     * Releases a previously acquired slot.
     */
    public function release(): void;

    public function available(): int;
}
