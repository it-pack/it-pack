<?php

declare(strict_types=1);

namespace Tests\Functional\Resilience;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Domain\Exception\RateLimitExceededException;
use ItPack\AsyncIt\Domain\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\RateLimiter
 */
final class RateLimiterTest extends TestCase
{
    public function testRateLimiterConsumesTokensUpToCapacity(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(3, 10, $clock);

        self::assertTrue($limiter->tryConsume());
        self::assertTrue($limiter->tryConsume());
        self::assertTrue($limiter->tryConsume());
        self::assertFalse($limiter->tryConsume());
    }

    public function testRateLimiterWaitForPermitReturnsZeroWhenTokenAvailable(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        $delay = $limiter->waitForPermit();
        self::assertSame(0, $delay->nanoseconds());
    }

    public function testRateLimiterConsumeThrowsWhenNoTokens(): void
    {
        $clock = new DeterministicMonotonicClock();
        $limiter = new RateLimiter(1, 10, $clock);

        self::assertTrue($limiter->tryConsume());

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume();
    }

    public function testRateLimiterZeroCapacityThrows(): void
    {
        $clock = new DeterministicMonotonicClock();
        $this->expectException(RateLimitExceededException::class);
        new RateLimiter(0, 10, $clock);
    }
}
