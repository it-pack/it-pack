<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Verifier\MonotonicClockVerifier;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\Conformance\FixedDuration;

/**
 * Feeds the {@see MonotonicClockVerifier} broken clock implementations and
 * asserts each contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
final class MonotonicClockVerifierNegativeTest extends Unit
{
    public function testThrowingNowSecondsIsReportedAndStopsTheCheck(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                throw new RuntimeException('wall clock unavailable');
            }

            public function monotonicNanoseconds(): int
            {
                throw new RuntimeException('must never be reached');
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                throw new RuntimeException('must never be reached');
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['nowSeconds.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testThrowingMonotonicNanosecondsIsReportedAndStopsTheCheck(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                throw new RuntimeException('counter unavailable');
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                throw new RuntimeException('must never be reached');
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['monotonicNanoseconds.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testDecreasingReadingIsReportedAndSamplingStops(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            /** @var list<int> */
            private array $readings = [10, 10, 10, 10, 4];

            private int $index = 0;

            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return $this->readings[$this->index++];
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return new FixedDuration($toInclusive - $fromInclusive);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['monotonicNanoseconds.not-monotonic'], ConformanceViolations::ids($result));
    }

    public function testThrowingMeasureIsReportedAndStopsTheCheck(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 5;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                throw new RuntimeException('measure unavailable');
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['measure.throws'], ConformanceViolations::ids($result));
    }

    public function testNonZeroIdenticalPairDurationIsReported(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 5;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return new FixedDuration($fromInclusive === $toInclusive ? 7 : 150);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['measure.zero'], ConformanceViolations::ids($result));
    }

    public function testNegativeIncreasingPairDurationIsReported(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 5;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return new FixedDuration($fromInclusive === $toInclusive ? 0 : -3);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame(['measure.negative'], ConformanceViolations::ids($result));
    }
}
