<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication\Policy;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;

/**
 * Sampling policy that lets errors override an ordinary decision.
 *
 * When the artifact is an error-or-above observation the policy keeps it
 * unconditionally, so failures are never lost to sampling. All other artifacts defer
 * to the wrapped policy. This is the canonical way to combine a ratio or severity policy
 * with a "keep all errors" guarantee.
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
        if ($artifact instanceof ObservationInterface && $artifact->severity()->isError()) {
            return true;
        }

        return $this->inner->sample($artifact);
    }
}
