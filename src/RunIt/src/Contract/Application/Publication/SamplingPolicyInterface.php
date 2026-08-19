<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for a policy that decides whether an artifact is sampled.
 *
 * Sampling reduces volume before publication. A policy returns `true` to keep the
 * artifact and `false` to drop it. Policies must be deterministic given the same
 * artifact and the same internal state so tests can assert on the decision.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface SamplingPolicyInterface
{
    /**
     * Returns whether the artifact should be sampled (kept).
     *
     * Implementations must not throw for any artifact kind.
     */
    public function sample(ExecutionArtifactInterface $artifact): bool;
}
