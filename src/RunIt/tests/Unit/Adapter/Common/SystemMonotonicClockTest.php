<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SystemMonotonicClock::class)]
final class SystemMonotonicClockTest extends Unit
{
    public function testNowSeconds(): void
    {
        self::assertGreaterThan(0, new SystemMonotonicClock()->nowSeconds());
    }

    public function testMonotonicNanoseconds(): void
    {
        self::assertGreaterThan(0, new SystemMonotonicClock()->monotonicNanoseconds());
    }

    public function testMeasureReturnsDuration(): void
    {
        self::assertSame('0 ns', new SystemMonotonicClock()->measure(5, 5)->toString());
    }
}
