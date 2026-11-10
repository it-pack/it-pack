<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Verifier\MonotonicClockVerifier;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use RuntimeException;
use Tests\Support\Fixture\DeterministicMonotonicClock;

/**
 * @group conformance
 */
final class MonotonicClockVerifierTest extends Unit
{
    public function testPortReportsMonotonicClockInterface(): void
    {
        self::assertSame(MonotonicClockInterface::class, new MonotonicClockVerifier()->port());
    }

    public function testConformantClockPasses(): void
    {
        $clock = new DeterministicMonotonicClock(1_700_000_000, 100);

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testNowSecondsThrowIsRecorded(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                throw new RuntimeException('nowSeconds broken');
            }

            public function monotonicNanoseconds(): int
            {
                return 0;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds(0);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('nowSeconds.throws', $result->errors()[0]->id);
    }

    public function testNotMonotonicIsRecorded(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            private int $reading = 1000;

            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return $this->reading -= 100;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds(0);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame('monotonicNanoseconds.not-monotonic', $result->errors()[0]->id);
    }

    public function testMeasureThrowIsRecorded(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 100;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                throw new RuntimeException('measure broken');
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame('measure.throws', $result->errors()[0]->id);
    }

    public function testMeasureZeroIsRecorded(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 100;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds(5);
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertSame('measure.zero', $result->errors()[0]->id);
    }

    public function testMeasureNegativeIsRecorded(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 1_700_000_000;
            }

            public function monotonicNanoseconds(): int
            {
                return 100;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return new class () implements DurationInterface {
                    public function nanoseconds(): int
                    {
                        return -3;
                    }

                    public function seconds(): int
                    {
                        return 0;
                    }

                    public function toString(): string
                    {
                        return '-3 ns';
                    }
                };
            }
        };

        $result = new MonotonicClockVerifier()->verify($clock);

        self::assertFalse($result->isConformant());
        self::assertCount(2, $result->errors());
        self::assertSame('measure.zero', $result->errors()[0]->id);
        self::assertSame('measure.negative', $result->errors()[1]->id);
    }
}
