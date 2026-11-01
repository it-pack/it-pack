<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\RunIt\Contract\Domain\DurationInterface;

final readonly class FakeDuration implements DurationInterface
{
    public function __construct(private int $nanos)
    {
    }

    public function nanoseconds(): int
    {
        return $this->nanos;
    }

    public function seconds(): int
    {
        return (int) floor($this->nanos / 1_000_000_000);
    }

    public function toString(): string
    {
        return $this->nanos . 'ns';
    }

    public function equals(DurationInterface $other): bool
    {
        return $other->nanoseconds() === $this->nanos;
    }
}
