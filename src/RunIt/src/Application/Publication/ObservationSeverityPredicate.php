<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Shared severity-floor predicate for observations.
 *
 * The observation severity filter and the severity sampling policy apply
 * the identical threshold, so the logic lives here once and both callers delegate.
 * Non-observation artifacts pass, so a severity floor drops only noisy observations
 * and never suppresses measurements, occurrences, or snapshots.
 *
 * Genuine {@see ObservationInterface} instances are subject to the severity floor.
 * Trusted projections and all {@see RedactedArtifactInterface} instances reporting
 * an observation type are checked using their normalized level or severity. The
 * selected value must be an integer at or above the floor; otherwise it is rejected.
 * User-supplied redacted artifacts can control their type and normalized fields.
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
        if ($artifact instanceof ObservationInterface) {
            return $artifact->severity()->level() >= $this->minLevel;
        }

        if (($artifact instanceof TrustedArtifactProjectionInterface || $artifact instanceof VerifiedArtifactProjection)
            && $artifact->artifactType() === 'observation') {
            $normalized = $artifact->normalize();
            $level = $normalized[ArtifactField::LEVEL] ?? $normalized[ArtifactField::SEVERITY] ?? null;

            return is_int($level) && $level >= $this->minLevel;
        }

        if ($artifact instanceof RedactedArtifactInterface
            && $artifact->artifactType() === 'observation') {
            $normalized = $artifact->normalize();
            $level = $normalized[ArtifactField::LEVEL] ?? $normalized[ArtifactField::SEVERITY] ?? null;

            return is_int($level) && $level >= $this->minLevel;
        }

        return true;
    }
}
