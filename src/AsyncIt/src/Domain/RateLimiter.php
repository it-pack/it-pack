<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Domain\Exception\RateLimitExceededException;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;

/**
 * Token-bucket rate limiter using a monotonic clock.
 *
 * Tokens accrue at {@see $refillRatePerSecond} up to {@see $capacity}.
 * Each call to {@see consume()} consumes one token. Callers use
 * {@see waitForPermit()} to determine if they must suspend; the actual
 * Fiber suspension is performed by the scheduler via awaitDelay.
 *
 * @see https://w3id.org/itpack/asyncit#RateLimiter
 */
final class RateLimiter implements RateLimiterInterface
{
    private int $tokens;

    private int $lastRefillMonotonic;

    public function __construct(
        public readonly int $capacity,
        public readonly int $refillRatePerSecond,
        private readonly MonotonicClockInterface $clock,
    ) {
        if ($capacity <= 0 || $refillRatePerSecond <= 0) {
            throw new RateLimitExceededException('Rate limiter capacity and refill rate must be positive.');
        }

        $this->tokens = $capacity;
        $this->lastRefillMonotonic = $clock->monotonicNanoseconds();
    }

    /**
     * Attempts to consume a token without blocking.
     */
    public function tryConsume(): bool
    {
        $this->refill();

        if ($this->tokens > 0) {
            --$this->tokens;

            return true;
        }

        return false;
    }

    /**
     * Computes the delay needed to acquire a token. Returns zero when a
     * token is immediately available, or a Duration describing how long to
     * wait before calling {@see consume()}.
     */
    public function waitForPermit(): DurationInterface
    {
        $this->refill();

        if ($this->tokens > 0) {
            return Duration::ofNanoseconds(0);
        }

        $nanosPerToken = 1_000_000_000 / $this->refillRatePerSecond;
        $tokensNeeded = 1 - $this->tokens;
        $delayNanos = (int) ($tokensNeeded * $nanosPerToken);

        return Duration::ofNanoseconds($delayNanos);
    }

    /**
     * Consumes a token immediately. Throws if none available; callers should
     * await the delay from {@see waitForPermit()} first in a Fiber context.
     *
     * @throws RateLimitExceededException
     */
    public function consume(): void
    {
        if (!$this->tryConsume()) {
            throw new RateLimitExceededException();
        }
    }

    private function refill(): void
    {
        $now = $this->clock->monotonicNanoseconds();
        $elapsed = $now - $this->lastRefillMonotonic;

        if ($elapsed <= 0) {
            return;
        }

        $nanosPerToken = 1_000_000_000 / $this->refillRatePerSecond;
        $tokensToAdd = (int) ($elapsed / $nanosPerToken);

        if ($tokensToAdd > 0) {
            $this->tokens = min($this->capacity, $this->tokens + $tokensToAdd);
            $this->lastRefillMonotonic += (int) ($tokensToAdd * $nanosPerToken);
        }
    }
}
