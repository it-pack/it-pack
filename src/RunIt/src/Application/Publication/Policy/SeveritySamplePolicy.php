<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication\Policy;

use ItPack\RunIt\Application\Publication\ObservationSeverityPredicate;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Sampling policy that keeps observations at or above a minimum PSR-3 level.
 *
 * Non-observation artifacts are always kept, so a severity floor drops only noisy
 * observations and should never suppress measurements, occurrences, or snapshots.
 * The threshold logic is shared with {@see ObservationSeverityFilter} via
 * {@see ObservationSeverityPredicate}.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class SeveritySamplePolicy implements SamplingPolicyInterface
{
    private ArtifactPredicateInterface $predicate;

    public function __construct(int $minLevel)
    {
        $this->predicate = new ObservationSeverityPredicate($minLevel);
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        return $this->predicate->test($artifact);
    }
}
