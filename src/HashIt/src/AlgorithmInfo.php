<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Immutable, read-only view of a HashAlgorithm's strength metadata.
 *
 * Constructed from the #[AlgorithmMetadata] attribute; exposes convenience
 * predicates so callers never have to compare against raw enum values.
 */
final readonly class AlgorithmInfo
{
    public function __construct(
        private int $outputBits,
        private int $blockBits,
        private AlgorithmStatus $status,
    ) {
    }

    public function outputBits(): int
    {
        return $this->outputBits;
    }

    public function blockBits(): int
    {
        return $this->blockBits;
    }

    public function status(): AlgorithmStatus
    {
        return $this->status;
    }

    public function isBroken(): bool
    {
        return $this->status === AlgorithmStatus::Broken;
    }

    public function isRecommended(): bool
    {
        return $this->status === AlgorithmStatus::Recommended;
    }
}
