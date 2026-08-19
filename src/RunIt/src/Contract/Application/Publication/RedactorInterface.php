<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for a redactor that returns a safe projection of an artifact.
 *
 * Redaction removes or masks sensitive content before external publication. The
 * redactor must never mutate the receiver: when it changes content it returns a
 * new artifact and leaves the original immutable artifact untouched.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RedactorInterface
{
    /**
     * Returns a redacted projection of the artifact.
     *
     * When nothing needs redaction the redactor may return the same artifact
     * instance; otherwise it returns a new, redacted artifact.
     */
    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface;
}
