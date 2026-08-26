<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Artifact whose {@see normalize()} output changes between calls, used to prove
 * that verifiers detect in-place mutation of a supposedly immutable artifact.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ImpureNormalizeArtifact implements ExecutionArtifactInterface
{
    private int $invocations = 0;

    public function normalize(): array
    {
        return ['normalize.invocations' => ++$this->invocations];
    }
}
