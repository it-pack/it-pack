<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

/**
 * Failure policy that rethrows the publication error.
 *
 * This is the fail-closed policy: when publication is mandatory (for example a
 * compliance sink) a transport failure must surface rather than be hidden. The policy
 * rethrows the original adapter exception unchanged; it never substitutes a business
 * exception.
 *
 * @see https://w3id.org/itpack/runit#PublicationFailurePolicy
 */
final class FailClosedPolicy implements PublicationFailurePolicyInterface
{
    public function handle(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        throw $error;
    }
}
