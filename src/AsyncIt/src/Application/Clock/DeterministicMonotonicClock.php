<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Clock;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;

/**
 * Deterministic monotonic clock for the reference runtime and duration
 * assertions without sleeping.
 *
 * Time only advances when {@see self::advance()} is called, so tests can drive
 * exact elapsed-time scenarios. Wall-clock (`nowSeconds`) is a fixed value and
 * never feeds duration math — monotonic only.
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
  * @api

  */
final class DeterministicMonotonicClock implements MonotonicClockInterface, DeterministicMonotonicClockInterface
{
    private int $nanoseconds = 0;

    public function __construct(private readonly int $startSeconds = 0)
    {
    }

    public function nowSeconds(): int
    {
        return $this->startSeconds;
    }

    public function monotonicNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function measure(int $fromInclusive, int $toInclusive): DurationInterface
    {
        return Duration::ofNanoseconds(max(0, $toInclusive - $fromInclusive));
    }

    public function advance(int $nanoseconds): void
    {
        $this->nanoseconds += $nanoseconds;
    }
}
