<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;

/**
 * A redacted projection of an artifact.
 *
 * The wrapper carries the redacted normalization of the original artifact and
 * never mutates the original. It implements {@see ExecutionArtifactInterface} and
 * the {@see RedactedArtifactInterface} marker so any publisher can recognize it as
 * a redacted signal. Consumers route using {@see artifactType()}, but the value
 * alone does not establish provenance: instances can be constructed by callers.
 * Provenance must be verified upstream where it is required.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactedArtifact implements ExecutionArtifactInterface, RedactedArtifactInterface
{
    /**
     * @param array<string, mixed> $normalized the redacted normalization
     * @param string               $artifactType artifact kind supplied by the producer
     */
    public function __construct(
        private array $normalized,
        private string $artifactType = 'unknown',
    ) {
    }

    public function normalize(): array
    {
        return $this->normalized;
    }

    /**
     * The artifact kind this redacted projection represents.
     */
    public function artifactType(): string
    {
        return $this->artifactType;
    }
}
