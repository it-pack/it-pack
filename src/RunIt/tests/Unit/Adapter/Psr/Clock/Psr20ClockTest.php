<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Clock;

use Codeception\Test\Unit;
use DateTimeImmutable;
use ItPack\RunIt\Adapter\Psr\Clock\Psr20Clock;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Clock\ClockInterface;

#[CoversClass(Psr20Clock::class)]
#[UsesClass(Duration::class)]
final class Psr20ClockTest extends Unit
{
    private function clockAt(int $epochSeconds): ClockInterface
    {
        return new readonly class ($epochSeconds) implements ClockInterface {
            public function __construct(private int $epoch)
            {
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('@' . $this->epoch);
            }
        };
    }

    public function testWallClockComesFromPsr20Clock(): void
    {
        $clock = new Psr20Clock($this->clockAt(1_700_000_000));

        self::assertSame(1_700_000_000, $clock->nowSeconds());
    }

    public function testMonotonicTimeIsIndependentOfWallClock(): void
    {
        $clock = new Psr20Clock($this->clockAt(1_700_000_000));

        self::assertNotEquals(1_700_000_000, $clock->monotonicNanoseconds());
        self::assertGreaterThan(0, $clock->monotonicNanoseconds());
    }

    public function testMeasureUsesMonotonicReadings(): void
    {
        $clock = new Psr20Clock($this->clockAt(1_700_000_000));

        $duration = $clock->measure(0, 1_000_000_000);

        self::assertSame(1, $duration->seconds());
        self::assertSame(1_000_000_000, $duration->nanoseconds());
    }
}
