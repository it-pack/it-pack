<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;

/**
 * Sampling policy that keeps observations at or above a minimum PSR-3 level.
 *
 * Non-observation artifacts are always kept, so a severity floor drops only noisy
 * observations and never suppresses measurements, occurrences, or snapshots.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class SeveritySamplePolicy implements SamplingPolicyInterface
{
    public function __construct(
        private int $minLevel,
    ) {
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ObservationInterface) {
            return true;
        }

        return $artifact->severity()->level() >= $this->minLevel;
    }
}
