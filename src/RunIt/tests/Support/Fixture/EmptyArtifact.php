<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Minimal execution artifact whose normalization is an empty structure.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class EmptyArtifact implements ExecutionArtifactInterface
{
    public function normalize(): array
    {
        return [];
    }
}
