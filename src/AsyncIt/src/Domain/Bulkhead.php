<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\BulkheadInterface;
use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;

/**
 * Semaphore-style resource isolation bulkhead.
 *
 * Limits the number of concurrent operations (e.g. outbound calls to a
 * downstream service). The pure state machine is usable directly in
 * synchronous contexts; for cooperative yielding within a Fiber task,
 * use {@see waitForCapacity()} and suspend the Fiber after.
 *
 * @see https://w3id.org/itpack/asyncit#Bulkhead
 */
final class Bulkhead implements BulkheadInterface
{
    private const string NON_POSITIVE_CAPACITY_MESSAGE = 'Bulkhead capacity must be positive.';

    private const int MIN_CAPACITY = 1;

    private int $available;

    public function __construct(
        public readonly int $capacity,
    ) {
        if ($capacity < self::MIN_CAPACITY) {
            throw new BulkheadFullException(
                $capacity,
                self::NON_POSITIVE_CAPACITY_MESSAGE
            );
        }

        $this->available = $capacity;
    }

    /**
     * Attempts to acquire a slot without blocking.
     *
     * @throws BulkheadFullException when at capacity
     */
    public function tryAcquire(): void
    {
        if (!$this->isAvailable()) {
            throw new BulkheadFullException($this->capacity);
        }

        --$this->available;
    }

    /**
     * Attempts to acquire a slot. Returns true if acquired, false if at
     * capacity (caller should suspend/yield and retry).
     */
    public function waitForCapacity(): bool
    {
        if ($this->isAvailable()) {
            --$this->available;

            return true;
        }

        return false;
    }

    /**
     * Releases a previously acquired slot.
     */
    public function release(): void
    {
        if ($this->available < $this->capacity) {
            ++$this->available;
        }
    }

    public function available(): int
    {
        return $this->available;
    }

    private function isAvailable(): bool
    {
        return $this->available >= self::MIN_CAPACITY;
    }
}
