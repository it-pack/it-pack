<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for bounded security configuration values.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface SecurityLimitsInterface
{
    /**
     * The default attribute-count bound, shared with
     * {@see \ItPack\RunIt\Domain\Context\ExecutionContext::MAX_ENTRIES} so the two
     * entry ceilings cannot drift apart silently.
     */
    public const int DEFAULT_MAX_ATTRIBUTE_COUNT = 64;

    public int $maxAttributeCount {
        get;
    }

    public int $maxAttributeKeyBytes {
        get;
    }

    public int $maxAttributeValueBytes {
        get;
    }

    public int $maxAttributeDepth {
        get;

    }

    public int $maxOccurrencePayloadBytes {
        get;

    }

    public int $maxSnapshotPayloadBytes {
        get;

    }

    public int $maxActivityDepth {
        get;

    }

    public int $maxArtifactsPerExecution {
        get;

    }

    public int $maxSerializationDepth {
        get;

    }

    public int $maxSerializationBytes {
        get;

    }

    public int $maxTrackedExecutions {
        get;

    }

    public function withMaxAttributeCount(int $maxAttributeCount): self;

    public function withMaxAttributeKeyBytes(int $maxAttributeKeyBytes): self;

    public function withMaxAttributeValueBytes(int $maxAttributeValueBytes): self;

    public function withMaxAttributeDepth(int $maxAttributeDepth): self;

    public function withMaxOccurrencePayloadBytes(int $maxOccurrencePayloadBytes): self;

    public function withMaxSnapshotPayloadBytes(int $maxSnapshotPayloadBytes): self;

    public function withMaxActivityDepth(int $maxActivityDepth): self;

    public function withMaxArtifactsPerExecution(int $maxArtifactsPerExecution): self;

    public function withMaxSerializationDepth(int $maxSerializationDepth): self;

    public function withMaxSerializationBytes(int $maxSerializationBytes): self;

    public function withMaxTrackedExecutions(int $maxTrackedExecutions): self;
}
