<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Port supplying time to the domain.
 *
 * Time is an external effect. Wall-clock time (for timestamps) and monotonic
 * time (for durations) are kept separate on purpose: monotonic time is never
 * adjusted by NTP or manual changes and is therefore safe for measuring
 * elapsed time, while wall-clock time is what a timestamp records.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
interface MonotonicClockInterface
{
    /**
     * Returns the current Unix epoch seconds (wall-clock time).
     */
    public function nowSeconds(): int;

    /**
     * Returns the current monotonic time in nanoseconds.
     *
     * Only differences between two readings are meaningful; the absolute value
     * has no relation to wall-clock time.
     */
    public function monotonicNanoseconds(): int;

    /**
     * Measures the duration between two monotonic readings.
     */
    public function measure(int $fromInclusive, int $toInclusive): DurationInterface;
}
