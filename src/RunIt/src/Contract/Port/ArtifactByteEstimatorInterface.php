<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Estimates the serialized byte size of an artifact.
 *
 * Buffer bounds are expressed in estimated bytes, not item count alone, so a
 * few large artifacts cannot exhaust memory while many tiny ones wait. Estimation
 * is intentionally approximate: it never reads the wire format and never retains
 * the artifact. Adapters choose how pessimistic to be.
 *
 * Implementations must be safe to call on a hot path.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Artifact
 */
interface ArtifactByteEstimatorInterface
{
    /**
     * Returns an estimated serialized byte size in bytes.
     */
    public function estimate(ExecutionArtifactInterface $artifact): int;
}
