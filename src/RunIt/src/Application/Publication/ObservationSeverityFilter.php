<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;

/**
 * Filter that accepts observations at or above a minimum PSR-3 severity level.
 *
 * Non-observation artifacts always pass, so a severity floor drops only noisy
 * observations and never suppresses measurements, occurrences, or snapshots.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final class ObservationSeverityFilter implements ArtifactFilterInterface
{
    public function __construct(
        private readonly int $minLevel,
    ) {
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ObservationInterface) {
            return true;
        }

        return $artifact->severity()->level() >= $this->minLevel;
    }
}
