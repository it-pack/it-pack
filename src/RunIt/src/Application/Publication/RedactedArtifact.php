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
 * a suppressed signal; typed adapters that downcast to a concrete artifact type
 * should treat it as "suppressed" (emit a visible, non-sensitive delivery) rather
 * than silently dropping it. Redaction happens before mapping, which is exactly the
 * pipeline order this type enforces.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactedArtifact implements ExecutionArtifactInterface, RedactedArtifactInterface
{
    /**
     * @param array<string, mixed> $normalized the redacted normalization
     */
    public function __construct(
        private array $normalized,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return $this->normalized;
    }
}
