<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Application\Publication\Policy\SeveritySamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Filter that accepts observations at or above a minimum PSR-3 severity level.
 *
 * Non-observation artifacts always pass, so a severity floor drops only noisy
 * observations and never suppresses measurements, occurrences, or snapshots.
 * The threshold logic is shared with {@see SeveritySamplePolicy} via
 * {@see ObservationSeverityPredicate}.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class ObservationSeverityFilter implements ArtifactFilterInterface
{
    private ArtifactPredicateInterface $predicate;

    public function __construct(int $minLevel)
    {
        $this->predicate = new ObservationSeverityPredicate($minLevel);
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        return $this->predicate->test($artifact);
    }
}
