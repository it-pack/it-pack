<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;

/**
 * Deterministic byte estimator that returns a fixed size per artifact id.
 *
 * Used by buffer tests to assert byte-limit admission without depending on the
 * default estimator's JSON-length behavior. Explicit per-ID sizes override the
 * default size without making assumptions about identifier formats.
 *
 * @see https://w3id.org/itpack/runit#Artifact
 */
final class DeterministicArtifactByteEstimator implements ArtifactByteEstimatorInterface
{
    /** @param array<string, int> $sizesById */
    public function __construct(
        private readonly int $baseBytes = 100,
        private array $sizesById = [],
    ) {
    }

    public function estimate(ExecutionArtifactInterface $artifact): int
    {
        $normalized = $artifact->normalize();
        $id = $normalized['id'] ?? null;

        if (!is_string($id)) {
            return $this->baseBytes;
        }

        return $this->sizesById[$id] ?? $this->baseBytes;
    }
}
