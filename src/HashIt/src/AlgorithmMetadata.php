<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Machine-readable strength metadata attached to a HashAlgorithm case.
 *
 * Pure, read-only data — carrying this attribute changes no hashing behaviour.
 * Later milestones (V1, H2, H6) consume it to steer safer algorithm choices.
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final readonly class AlgorithmMetadata
{
    /**
     * @param int             $outputBits Digest size in bits (e.g. SHA-256 → 256).
     * @param int             $blockBits  Internal block/rate size in bits (0 for non-block primitives).
     * @param AlgorithmStatus $status     Collision / cryptographic strength rating.
     */
    public function __construct(
        public int $outputBits,
        public int $blockBits,
        public AlgorithmStatus $status,
    ) {
    }
}
