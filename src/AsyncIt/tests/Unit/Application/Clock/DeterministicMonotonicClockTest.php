<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Clock;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock
 */
final class DeterministicMonotonicClockTest extends TestCase
{
    public function testImplementsMonotonicClockInterface(): void
    {
        $clock = new DeterministicMonotonicClock();

        self::assertInstanceOf(MonotonicClockInterface::class, $clock);
    }

    public function testNowSecondsReturnsConstructorValue(): void
    {
        $clock = new DeterministicMonotonicClock(42);

        self::assertSame(42, $clock->nowSeconds());
    }

    public function testMonotonicNanosecondsStartsAtZero(): void
    {
        $clock = new DeterministicMonotonicClock();

        self::assertSame(0, $clock->monotonicNanoseconds());
    }

    public function testAdvanceIncrementsNanoseconds(): void
    {
        $clock = new DeterministicMonotonicClock();
        $clock->advance(1_000_000_000);

        self::assertSame(1_000_000_000, $clock->monotonicNanoseconds());
    }

    public function testMeasureReturnsDuration(): void
    {
        $clock = new DeterministicMonotonicClock();

        $duration = $clock->measure(0, 500);

        self::assertSame(500, $duration->nanoseconds());
    }

    public function testMeasureClampsNegativeDurationToZero(): void
    {
        $clock = new DeterministicMonotonicClock();

        $duration = $clock->measure(500, 100);

        self::assertSame(0, $duration->nanoseconds());
    }
}
