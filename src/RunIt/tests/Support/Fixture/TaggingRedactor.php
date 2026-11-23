<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Redactor spy that wraps published artifacts in a RedactedTagArtifact and counts calls.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class TaggingRedactor implements RedactorInterface
{
    public int $calls = 0;

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        ++$this->calls;

        return new RedactedTagArtifact($artifact);
    }
}
