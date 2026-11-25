<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Duration with a fixed nanosecond magnitude, used by clock verifier tests.
 *
 * @see https://w3id.org/itpack/runit#duration
 */
final readonly class FixedDuration implements DurationInterface
{
    public function __construct(private int $nanoseconds)
    {
    }

    public function nanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function microseconds(): int
    {
        return intdiv($this->nanoseconds, 1_000);
    }

    public function milliseconds(): int
    {
        return intdiv($this->nanoseconds, 1_000_000);
    }

    public function seconds(): int
    {
        return intdiv($this->nanoseconds, 1_000_000_000);
    }

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
        return $this->nanoseconds . 'ns';
    }
}
