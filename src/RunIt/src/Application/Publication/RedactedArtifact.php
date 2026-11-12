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
 * a suppressed signal. The {@see artifactType()} is the trusted delivery signal:
 * it is set by the redactor that produced the projection and must be used by
 * adapters to route redacted artifacts, never inferred from user-controlled
 * normalized fields. Redaction happens before mapping, which is exactly the
 * pipeline order this type enforces.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactedArtifact implements ExecutionArtifactInterface, RedactedArtifactInterface
{
    /**
     * @param array<string, mixed> $normalized the redacted normalization
     * @param string               $artifactType trusted artifact kind set by the redactor
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
     * The trusted artifact kind this redacted projection represents.
     */
    public function artifactType(): string
    {
        return $this->artifactType;
    }
}
