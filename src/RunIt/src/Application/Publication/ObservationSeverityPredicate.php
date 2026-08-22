<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;

/**
 * Shared severity-floor predicate for observations.
 *
 * The observation severity filter and the severity sampling policy apply
 * the identical threshold, so the logic lives here once and both callers delegate.
 * Non-observation artifacts pass, so a severity floor drops only noisy observations
 * and never suppresses measurements, occurrences, or snapshots.
 *
 * @internal
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class ObservationSeverityPredicate implements ArtifactPredicateInterface
{
    public function __construct(private int $minLevel)
    {
    }

    public function test(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ObservationInterface) {
            return true;
        }

        return $artifact->severity()->level() >= $this->minLevel;
    }
}
