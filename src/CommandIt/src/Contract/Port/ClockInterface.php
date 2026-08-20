<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Port supplying time to the domain.
 *
 * Wall-clock time (timestamps) and monotonic time (durations) are kept separate
 * on purpose: monotonic time is never adjusted by NTP or manual changes and is
 * therefore safe for measuring elapsed time.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
interface ClockInterface
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
}
