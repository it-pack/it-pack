<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication\Policy;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Sampling policy that keeps every artifact.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class AlwaysSamplePolicy implements SamplingPolicyInterface
{
    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        return true;
    }
}
