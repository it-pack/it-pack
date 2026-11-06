<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Clock;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Psr\Clock\ClockInterface;

/**
 * Wall-clock and monotonic time backed by a PSR-20 clock.
 *
 * The two time sources are kept deliberately separate, exactly as the
 * {@see MonotonicClockInterface} contract requires:
 *
 *  - wall-clock time (used for timestamps) is read from the injected
 *    {@see ClockInterface} via `now()`;
 *  - monotonic time (used only for measuring elapsed durations) is read from
 *    `hrtime()` and never mixed with the wall clock.
 *
 * A PSR-20 clock cannot supply a monotonic source, so durations use the system
 * monotonic clock while timestamps honour the injected clock. This lets a test
 * or framework supply a fixed or virtual wall clock without corrupting duration
 * measurements.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
final readonly class Psr20Clock implements MonotonicClockInterface
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function nowSeconds(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    public function monotonicNanoseconds(): int
    {
        return hrtime(as_number: true);
    }

    public function measure(int $fromInclusive, int $toInclusive): DurationInterface
    {
        return Duration::ofNanoseconds($toInclusive - $fromInclusive);
    }
}
