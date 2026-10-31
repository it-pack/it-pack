<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;
use ItPack\AsyncIt\Domain\Bulkhead;
use ItPack\AsyncIt\Domain\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\Bulkhead
 * @covers \ItPack\AsyncIt\Domain\RateLimiter
 */
final class BulkheadTest extends TestCase
{
    public function testAcquiresSlots(): void
    {
        $bulkhead = new Bulkhead(2);

        self::assertSame(2, $bulkhead->available());

        $bulkhead->tryAcquire();

        self::assertSame(1, $bulkhead->available());
    }

    public function testThrowsWhenAtCapacity(): void
    {
        $bulkhead = new Bulkhead(1);
        $bulkhead->tryAcquire();

        $this->expectException(BulkheadFullException::class);

        $bulkhead->tryAcquire();
    }

    public function testReleaseRestoresCapacity(): void
    {
        $bulkhead = new Bulkhead(1);

        $bulkhead->tryAcquire();
        self::assertSame(0, $bulkhead->available());

        $bulkhead->release();
        self::assertSame(1, $bulkhead->available());

        $bulkhead->release();
        self::assertSame(1, $bulkhead->available());
    }

    public function testWaitForCapacityReturnsTrueWhenAvailable(): void
    {
        $bulkhead = new Bulkhead(1);

        self::assertTrue($bulkhead->waitForCapacity());
        self::assertSame(0, $bulkhead->available());
    }

    public function testWaitForCapacityReturnsFalseWhenAtCapacity(): void
    {
        $bulkhead = new Bulkhead(1);
        $bulkhead->tryAcquire();

        self::assertFalse($bulkhead->waitForCapacity());
    }

    public function testRateLimiterAllowsConsumeUnderCapacity(): void
    {
        $limiter = new RateLimiter(
            10,
            1,
            new DeterministicMonotonicClock(),
        );

        self::assertTrue($limiter->tryConsume());
        self::assertTrue($limiter->tryConsume());
    }

    public function testRateLimiterRejectsWhenEmpty(): void
    {
        $limiter = new RateLimiter(
            1,
            1,
            new DeterministicMonotonicClock(),
        );

        self::assertTrue($limiter->tryConsume());
        self::assertFalse($limiter->tryConsume());
    }
}
