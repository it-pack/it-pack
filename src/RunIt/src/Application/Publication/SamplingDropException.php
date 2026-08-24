<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Internal drop notice raised when a sampling policy rejects an artifact.
 *
 * This is not a failure of the publication pipeline: dropping is the sampling
 * policy's intended decision. The notice exists so drop counts stay observable
 * through the internal health reporter as a recognizably RunIt artifact instead
 * of a generic SPL exception.
 *
 * @internal
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SamplingDropException extends RuntimeException implements RunItExceptionInterface
{
    public static function forArtifact(): self
    {
        return new self('Artifact dropped by sampling policy.');
    }
}
