<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * Estimates the enforced payload-section size of an artifact without encoding.
 *
 * The bounded publisher checks occurrence `data` and snapshot `payload` sizes
 * against their limits on every publish. Measuring them by JSON-encoding would
 * serialize every artifact twice on the hottest path in the pipeline; this
 * estimator walks the primitive payload natively instead. Like every estimator,
 * it is deliberately approximate: it bounds memory, it does not report wire
 * sizes.
 *
 * Recursion is capped at {@see SecurityLimits::maxSerializationDepth}; a subtree
 * deeper than the cap counts as a single opaque value instead of walking further.
 *
 * @internal default collaborator of {@see BoundedArtifactPublisher}.
 */
final readonly class PayloadSectionByteEstimator implements ArtifactByteEstimatorInterface
{
    /**
     * Approximate byte cost of a value whose true encoded size is unknowable.
     */
    private const int OPAQUE_VALUE_BYTES = 64;

    private const int INT_BYTES = 8;

    private const int FLOAT_BYTES = 8;

    private const int BOOL_BYTES = 1;

    private const int NULL_BYTES = 0;

    public function __construct(
        private SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function estimate(ExecutionArtifactInterface $artifact): int
    {
        return match (true) {
            $artifact instanceof OccurrenceInterface => $this->sizeOf($artifact->data(), 1),
            $artifact instanceof SnapshotInterface => $this->sizeOf($artifact->payload(), 1),
            default => self::NULL_BYTES,
        };
    }

    private function sizeOf(mixed $value, int $depth): int
    {
        if (is_string($value)) {
            return strlen($value);
        }

        if (is_array($value)) {
            if ($depth > $this->limits->maxSerializationDepth) {
                // Depth cap: stop walking and charge the subtree as opaque, so a
                // deeply nested (or cyclic, were validation ever bypassed)
                // structure cannot recurse without bound.
                return self::OPAQUE_VALUE_BYTES;
            }

            $bytes = 0;

            foreach ($value as $key => $member) {
                $bytes += is_string($key) ? strlen($key) : self::INT_BYTES;
                $bytes += $this->sizeOf($member, $depth + 1);
            }

            return $bytes;
        }

        return match (true) {
            is_int($value) => self::INT_BYTES,
            is_float($value) => self::FLOAT_BYTES,
            is_bool($value) => self::BOOL_BYTES,
            $value === null => self::NULL_BYTES,
            default => self::OPAQUE_VALUE_BYTES,
        };
    }
}
