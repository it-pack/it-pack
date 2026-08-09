<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Behavior\Domain\TimestampedBehavior;
use ItPack\RunIt\Contract\Domain\TimestampedInterface;

/**
 * A timestamped artifact with a fixed point in time.
 *
 * Named rather than anonymous so that static analysis can bind the behavior
 * trait to a concrete class.
 */
final readonly class FixedTimestampedArtifact implements TimestampedInterface
{
    use TimestampedBehavior;

    /**
     * @param int<0, max> $timestamp
     */
    public function __construct(private int $timestamp)
    {
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
