<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Sampling policy that drops every artifact.
 *
 * Useful as the terminal branch of a composed policy and for tests that assert on
 * the drop path.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class NeverSamplePolicy implements SamplingPolicyInterface
{
    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        return false;
    }
}
