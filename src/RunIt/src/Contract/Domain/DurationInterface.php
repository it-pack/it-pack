<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a non-negative elapsed time.
 *
 * A duration is always measured with monotonic time so it is unaffected by
 * wall-clock adjustments, and is represented internally as integer nanoseconds.
 * It is never negative.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#duration
 */
interface DurationInterface
{
    /**
     * Returns the duration in whole nanoseconds.
     */
    public function nanoseconds(): int;

    /**
     * Returns the duration in whole seconds, rounding down.
     */
    public function seconds(): int;

    /**
     * Returns a human-readable, implementation-defined string representation.
     */
    public function toString(): string;
}
