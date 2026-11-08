<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\Fixture\DeterministicMonotonicClock;

#[CoversClass(SystemMonotonicClock::class)]
#[CoversClass(DeterministicMonotonicClock::class)]
#[UsesClass(Duration::class)]
final class MonotonicClockTest extends Unit
{
    public function testDeterministicClockReturnsSeededValues(): void
    {
        $clock = new DeterministicMonotonicClock(1_700_000_000, 5_000);

        self::assertSame(1_700_000_000, $clock->nowSeconds());
        self::assertSame(5_000, $clock->monotonicNanoseconds());
    }

    public function testWallClockAndMonotonicTimeAreIndependent(): void
    {
        $clock = new DeterministicMonotonicClock(1_700_000_000, 0);

        $clock->setNowSeconds(1_700_000_050);
        $clock->advanceSeconds(10);
        $clock->advanceNanoseconds(1_000);

        self::assertSame(1_700_000_060, $clock->nowSeconds());
        self::assertSame(1_000, $clock->monotonicNanoseconds());
    }

    public function testMeasureComputesElapsedTime(): void
    {
        $clock = new DeterministicMonotonicClock();
        $clock->setMonotonicNanoseconds(0);

        $duration = $clock->measure(0, 2_000_000_000);

        self::assertInstanceOf(DurationInterface::class, $duration);
        self::assertSame(2, $duration->seconds());
        self::assertSame(2_000_000_000, $duration->nanoseconds());
    }

    public function testMeasureClampsReversedIntervals(): void
    {
        $clock = new DeterministicMonotonicClock();

        $duration = $clock->measure(2_000_000_000, 1_000_000_000);

        self::assertSame(0, $duration->nanoseconds());
    }

    public function testSystemClockProducesIncreasingMonotonicReadings(): void
    {
        $clock = new SystemMonotonicClock();

        $first = $clock->monotonicNanoseconds();
        usleep(1);
        $second = $clock->monotonicNanoseconds();

        self::assertInstanceOf(DurationInterface::class, $clock->measure($first, $second));
        self::assertSame(0, $clock->measure($first, $first)->nanoseconds());
        self::assertGreaterThan($first, $second);
    }

    public function testSystemClockMeasuresAnElapsedDuration(): void
    {
        $clock = new SystemMonotonicClock();
        $from = $clock->monotonicNanoseconds();

        usleep(1_000);

        $duration = $clock->measure($from, $clock->monotonicNanoseconds());

        self::assertInstanceOf(Duration::class, $duration);
        self::assertGreaterThanOrEqual(1_000, $duration->nanoseconds());
    }
}
