<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;

/**
 * Deterministic clock for tests.
 *
 * Both the wall-clock seconds and the monotonic nanoseconds are settable, so a
 * test can advance time explicitly and assert on exact durations without sleeping.
 * It implements the same port as the production clock, so it drops in wherever a
 * {@see MonotonicClockInterface} is required.
 *
 * This is a test double: it is mutable on purpose and must never be used in
 * production code.
 */
final class DeterministicMonotonicClock implements MonotonicClockInterface
{
    public function __construct(private int $nowSeconds = 1_700_000_000, private int $monotonicNanoseconds = 0)
    {
    }

    public function setNowSeconds(int $nowSeconds): void
    {
        $this->nowSeconds = $nowSeconds;
    }

    public function setMonotonicNanoseconds(int $monotonicNanoseconds): void
    {
        $this->monotonicNanoseconds = $monotonicNanoseconds;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->nowSeconds += $seconds;
    }

    public function advanceNanoseconds(int $nanoseconds): void
    {
        $this->monotonicNanoseconds += $nanoseconds;
    }

    public function nowSeconds(): int
    {
        return $this->nowSeconds;
    }

    public function monotonicNanoseconds(): int
    {
        return $this->monotonicNanoseconds;
    }

    public function measure(int $fromInclusive, int $toInclusive): DurationInterface
    {
        $delta = $toInclusive < $fromInclusive ? 0 : $toInclusive - $fromInclusive;

        return Duration::ofNanoseconds($delta);
    }
}
