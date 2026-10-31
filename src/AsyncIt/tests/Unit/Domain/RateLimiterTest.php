<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Domain\Exception\RateLimitExceededException;
use ItPack\AsyncIt\Domain\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\RateLimiter
 */
final class RateLimiterTest extends TestCase
{
    public function testConstructorThrowsForNonPositiveCapacity(): void
    {
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limiter capacity and refill rate must be positive.');

        new RateLimiter(0, 1, new DeterministicMonotonicClock());
    }

    public function testConstructorThrowsForNonPositiveRefillRate(): void
    {
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limiter capacity and refill rate must be positive.');

        new RateLimiter(1, 0, new DeterministicMonotonicClock());
    }

    public function testConstructorThrowsForNegativeValues(): void
    {
        $this->expectException(RateLimitExceededException::class);

        new RateLimiter(-1, -1, new DeterministicMonotonicClock());
    }

    public function testTryConsumeReturnsTrueWhenTokensAvailable(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(2, 10, $clock);

        self::assertTrue($limiter->tryConsume());
    }

    public function testTryConsumeReturnsFalseWhenNoTokens(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $limiter->tryConsume();
        self::assertFalse($limiter->tryConsume());
    }

    public function testTryConsumeRefillsTokensAfterTimeAdvances(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 1, $clock);

        $limiter->tryConsume();
        self::assertFalse($limiter->tryConsume());

        $clock->advance(1_000_000_000);

        self::assertTrue($limiter->tryConsume());
    }

    public function testTryConsumeDoesNotExceedCapacityOnRefill(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(2, 1, $clock);

        $limiter->tryConsume();
        $clock->advance(5_000_000_000);

        self::assertTrue($limiter->tryConsume());
        self::assertTrue($limiter->tryConsume());
        self::assertFalse($limiter->tryConsume());
    }

    public function testConsumeSucceedsWhenTokenAvailable(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $limiter->consume();

        self::assertFalse($limiter->tryConsume());
    }

    public function testConsumeThrowsWhenNoTokenAvailable(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $limiter->consume();

        $this->expectException(RateLimitExceededException::class);

        $limiter->consume();
    }

    public function testWaitForPermitReturnsZeroWhenTokenAvailable(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $duration = $limiter->waitForPermit();

        self::assertSame(0, $duration->nanoseconds());
    }

    public function testWaitForPermitReturnsDelayWhenNoToken(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $limiter->tryConsume();
        $duration = $limiter->waitForPermit();

        self::assertSame(100_000_000, $duration->nanoseconds());
    }

    public function testWaitForPermitCalculatesDelayForMultipleTokensNeeded(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 2, $clock);

        $limiter->tryConsume();
        $limiter->tryConsume();
        $duration = $limiter->waitForPermit();

        self::assertSame(500_000_000, $duration->nanoseconds());
    }
}
