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
     * Returns the duration in whole microseconds, rounding down.
     */
    public function microseconds(): int;

    /**
     * Returns the duration in whole milliseconds, rounding down.
     */
    public function milliseconds(): int;

    /**
     * Returns the duration in whole seconds, rounding down.
     */
    public function seconds(): int;

    /**
     * Returns a duration that is this one plus another, without mutating either.
     */
    public function plus(DurationInterface $other): self;

    /**
     * Indicates whether this duration is equal to another.
     */
    public function equals(DurationInterface $other): bool;

    /**
     * Returns a human-readable, implementation-defined string representation.
     */
    public function toString(): string;
}
