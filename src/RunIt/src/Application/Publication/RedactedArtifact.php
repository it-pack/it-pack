<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * A redacted projection of an artifact.
 *
 * The wrapper carries the redacted normalization of the original artifact and
 * never mutates the original. It implements only {@see ExecutionArtifactInterface},
 * so it is safe to hand to any publisher that consumes the normalized form; typed
 * adapters that downcast to a concrete artifact type should redact before mapping,
 * which is exactly the pipeline order this type enforces.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactedArtifact implements ExecutionArtifactInterface
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
