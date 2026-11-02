<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Clock;

use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Deterministic monotonic clock surface for tests and the reference runtime.
 *
 * Backs {@see DeterministicMonotonicClock} so the clock remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 * @api
 */
interface DeterministicMonotonicClockInterface extends MonotonicClockInterface
{
    /**
     * Advances the clock by the given monotonic nanoseconds.
     */
    public function advance(int $nanoseconds): void;
}
