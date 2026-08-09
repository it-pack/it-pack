<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\SequencedInterface;

/**
 * A sequenced artifact with a fixed position within its execution.
 *
 * Named rather than anonymous so that static analysis can bind the behavior
 * trait to a concrete class.
 */
final readonly class FixedSequencedArtifact implements SequencedInterface
{
    use SequencedBehavior;

    /**
     * @param int<0, max> $sequence
     */
    public function __construct(private int $sequence)
    {
    }

    public function sequence(): int
    {
        return $this->sequence;
    }
}
