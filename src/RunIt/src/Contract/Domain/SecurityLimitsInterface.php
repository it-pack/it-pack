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
