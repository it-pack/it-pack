<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for a predicate that decides whether an artifact is published.
 *
 * Filters run early in the publication pipeline, before any expensive adapter
 * mapping, so they drop uninteresting artifacts cheaply. A filter must be a pure
 * function of the artifact: evaluating it must not retain the artifact or change
 * its state.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ArtifactFilterInterface
{
    /**
     * Returns whether the artifact should be published.
     *
     * Implementations must not throw for any artifact kind.
     */
    public function accepts(ExecutionArtifactInterface $artifact): bool;
}
