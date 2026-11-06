<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;

/**
 * A non-negative elapsed time, represented internally as integer nanoseconds.
 *
 * Durations are measured with monotonic time and are therefore unaffected by
 * wall-clock adjustments. The nanosecond representation keeps sub-microsecond
 * precision without floating-point rounding, at the cost of being limited to a
 * little over 292 years per duration value — ample for runtime measurement.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#duration
 */
final readonly class Duration implements DurationInterface
{
    public const int NANOS_PER_SECOND = 1_000_000_000;

    public const int NANOS_PER_MILLISECOND = 1_000_000;

    public const int NANOS_PER_MICROSECOND = 1_000;

    private function __construct(private int $nanoseconds)
    {
        if ($this->nanoseconds < 0) {
            throw InvalidStringValueException::negativeValue('duration nanoseconds');
        }
    }

    public static function ofNanoseconds(int $nanoseconds): self
    {
        return new self($nanoseconds);
    }

    public static function ofMicroseconds(int $microseconds): self
    {
        return new self($microseconds * self::NANOS_PER_MICROSECOND);
    }

    public static function ofMilliseconds(int $milliseconds): self
    {
        return new self($milliseconds * self::NANOS_PER_MILLISECOND);
    }

    public static function ofSeconds(int $seconds): self
    {
        return new self($seconds * self::NANOS_PER_SECOND);
    }

    public function nanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function microseconds(): int
    {
        return intdiv($this->nanoseconds, self::NANOS_PER_MICROSECOND);
    }

    public function milliseconds(): int
    {
        return intdiv($this->nanoseconds, self::NANOS_PER_MILLISECOND);
    }

    public function seconds(): int
    {
        return intdiv($this->nanoseconds, self::NANOS_PER_SECOND);
    }

    /**
     * Returns a duration that is this one plus another, without mutating either.
     */
    public function plus(DurationInterface $other): self
    {
        return new self($this->nanoseconds + $other->nanoseconds());
    }

    public function equals(DurationInterface $other): bool
    {
        return $this->nanoseconds === $other->nanoseconds();
    }

    public function toString(): string
    {
        return sprintf('%d ns', $this->nanoseconds);
    }
}
