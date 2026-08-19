<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for the policy applied when artifact publication fails.
 *
 * The pipeline invokes the policy after an inner publisher throws. The policy
 * decides the consequence: swallow the error (fail-open), rethrow it
 * (fail-closed), collect it for later inspection, or report it to a fallback
 * publisher. A fail-closed policy rethrows so the caller observes the original
 * adapter failure.
 *
 * The policy must not replace the original business exception with the
 * publication error; it may only decide what to do with the publication error.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PublicationFailurePolicyInterface
{
    /**
     * Handles a publication failure.
     *
     * @param \Throwable $error the original adapter or transport exception
     *
     * @throws \Throwable a fail-closed policy rethrows {@see $error}
     */
    public function handle(\Throwable $error, ExecutionArtifactInterface $artifact): void;
}
