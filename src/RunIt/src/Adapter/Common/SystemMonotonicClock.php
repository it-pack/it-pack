<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;

/**
 * Wall-clock and monotonic time backed by the system.
 *
 * `nowSeconds()` reads the wall clock via `time()` and is used for timestamps.
 * `monotonicNanoseconds()` reads `hrtime()` and is used only for measuring
 * elapsed time, never as an absolute point in time. The two sources are kept
 * separate so a wall-clock adjustment cannot corrupt a duration measurement.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
final class SystemMonotonicClock implements MonotonicClockInterface
{
    public function nowSeconds(): int
    {
        return time();
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
