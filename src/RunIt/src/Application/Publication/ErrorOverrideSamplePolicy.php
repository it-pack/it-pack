<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Sampling policy that lets errors override an ordinary decision.
 *
 * When the artifact is an error-or-above observation the policy keeps it
 * unconditionally, so failures are never lost to sampling. All other artifacts defer
 * to the wrapped policy. This is the canonical way to combine a ratio or severity policy
 * with a "keep all errors" guarantee.
 *
 * Genuine {@see ObservationInterface} instances are subject to the error override.
 * Trusted projections and all {@see RedactedArtifactInterface} instances reporting
 * an observation type use their normalized level or severity. An integer at or above
 * error overrides the inner policy; other values defer to it. User-supplied redacted
 * artifacts can control their type and normalized fields.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class ErrorOverrideSamplePolicy implements SamplingPolicyInterface
{
    public function __construct(
        private SamplingPolicyInterface $inner,
    ) {
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        if ($artifact instanceof ObservationInterface) {
            $level = $artifact->severity()->level();

            return $level >= ObservationSeverity::Error->level() || $this->inner->sample($artifact);
        }

        if (($artifact instanceof TrustedArtifactProjectionInterface || $artifact instanceof VerifiedArtifactProjection)
            && $artifact->artifactType() === 'observation') {
            $normalized = $artifact->normalize();
            $level = $normalized[ArtifactField::LEVEL] ?? $normalized[ArtifactField::SEVERITY] ?? null;

            return (is_int($level) && $level >= ObservationSeverity::Error->level()) || $this->inner->sample($artifact);
        }

        if ($artifact instanceof RedactedArtifactInterface
            && $artifact->artifactType() === 'observation') {
            $normalized = $artifact->normalize();
            $level = $normalized[ArtifactField::LEVEL] ?? $normalized[ArtifactField::SEVERITY] ?? null;

            return (is_int($level) && $level >= ObservationSeverity::Error->level()) || $this->inner->sample($artifact);
        }

        return $this->inner->sample($artifact);
    }
}
