<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Composable predicate over an execution artifact.
 *
 * Filters and sampling policies share resolution and threshold logic; this contract
 * is the single unit they are built from, so a decision (for example "severity at or
 * above a floor", or "owning execution kind within an allowed set") is implemented once
 * and reused by every caller. A predicate is a pure function of the artifact: it must
 * not retain the artifact or change its state.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ArtifactPredicateInterface
{
    /**
     * Returns whether the artifact satisfies the predicate.
     *
     * Implementations must not throw for any artifact kind.
     */
    public function test(ExecutionArtifactInterface $artifact): bool;
}
