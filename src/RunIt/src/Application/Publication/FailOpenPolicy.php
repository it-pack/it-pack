<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

/**
 * Failure policy that swallows the publication error.
 *
 * This is the fail-open default: a broken transport must not replace the operation's
 * business result or the original business exception. The error is intentionally dropped.
 *
 * @see https://w3id.org/itpack/runit#PublicationFailurePolicy
 */
final class FailOpenPolicy implements PublicationFailurePolicyInterface
{
    public function handle(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
    }
}
