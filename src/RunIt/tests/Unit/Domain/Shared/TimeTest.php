<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;

final class TimeTest extends Unit
{
    public function testInstantExposesEpochSeconds(): void
    {
        $instant = Instant::of(1_700_000_000);

        self::assertInstanceOf(InstantInterface::class, $instant);
        self::assertSame(1_700_000_000, $instant->epochSeconds());
        self::assertSame('1700000000', $instant->toString());
        self::assertSame('1700000000', (string) $instant);
    }

    public function testInstantNowReadsTheWallClock(): void
    {
        $before = time();
        $instant = Instant::now();
        $after = time();

        self::assertGreaterThanOrEqual($before, $instant->epochSeconds());
        self::assertLessThanOrEqual($after, $instant->epochSeconds());
    }

    public function testNegativeInstantIsRejected(): void
    {
        $this->expectException(InvalidStringValueException::class);

        // The parameter type is `int<0, max>`; a negative value reaches the
        // guard only via an unchecked cast, which is what this test asserts.
        Instant::of(-1);
    }

    public function testDurationConversionsRoundTrip(): void
    {
        $duration = Duration::ofSeconds(2);

        self::assertInstanceOf(DurationInterface::class, $duration);
        self::assertSame(2, $duration->seconds());
        self::assertSame(2_000, $duration->milliseconds());
        self::assertSame(2_000_000, $duration->microseconds());
        self::assertSame(2_000_000_000, $duration->nanoseconds());
    }

    public function testDurationFromEachUnitAgrees(): void
    {
        self::assertTrue(
            Duration::ofSeconds(1)->equals(Duration::ofMilliseconds(1000)),
        );
        self::assertTrue(
            Duration::ofMilliseconds(1)->equals(Duration::ofMicroseconds(1000)),
        );
        self::assertTrue(
            Duration::ofMicroseconds(1)->equals(Duration::ofNanoseconds(1000)),
        );
    }

    public function testDurationAdditionIsImmutable(): void
    {
        $base = Duration::ofSeconds(1);
        $sum = $base->plus(Duration::ofSeconds(2));

        self::assertSame(1, $base->seconds());
        self::assertSame(3, $sum->seconds());
        self::assertFalse($base->equals($sum));
    }

    public function testNegativeDurationIsRejected(): void
    {
        $this->expectException(InvalidStringValueException::class);

        Duration::ofNanoseconds(-1);
    }

    public function testDurationDescriptionIsStable(): void
    {
        self::assertSame('2000000000 ns', Duration::ofSeconds(2)->toString());
    }
}
