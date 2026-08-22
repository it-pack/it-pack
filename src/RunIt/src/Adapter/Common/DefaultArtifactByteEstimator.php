<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;

/**
 * Default artifact byte estimator.
 *
 * Estimates serialized size by normalizing the artifact and measuring the
 * JSON representation. The estimate is deliberately optimistic: it never blocks
 * on a real encoder and is only used to bound buffer memory, not to report exact
 * wire sizes.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#artifact
 */
final class DefaultArtifactByteEstimator implements ArtifactByteEstimatorInterface
{
    public function estimate(ExecutionArtifactInterface $artifact): int
    {
        return strlen((string) json_encode($artifact->normalize(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
    }
}
