<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use InvalidArgumentException;

/**
 * Bounded configuration for the data-protection controls of RunIt.
 *
 * Every bound is a safeguard against a data-exfiltration or denial-of-service path:
 * payloads that exceed the limit are dropped or rejected before they can leave the
 * process, attribute collections stay bounded in size and cardinality, and
 * executions cannot accumulate an unbounded number of artifacts.
 *
 * The values are intentionally conservative defaults. Consumers wire a tuned
 * instance through the SDK builder; the values are never silently widened.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SecurityLimits
{
    /**
     * Creates the default, conservative limits.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * @param int $maxAttributeCount          maximum attributes in one collection
     * @param int $maxAttributeKeyBytes       maximum attribute key length in bytes
     * @param int $maxAttributeValueBytes     maximum scalar attribute value length in bytes
     * @param int $maxAttributeDepth          maximum nesting depth of an attribute collection
     * @param int $maxOccurrencePayloadBytes  maximum occurrence `data` size in bytes
     * @param int $maxSnapshotPayloadBytes    maximum snapshot payload size in bytes
     * @param int $maxActivityDepth           maximum nested-activity depth per execution
     * @param int $maxArtifactsPerExecution   maximum artifacts recorded per execution
     * @param int $maxSerializationDepth      maximum depth of arbitrary data normalization
     * @param int $maxSerializationBytes      maximum size of an arbitrary serialized payload in bytes
     */
    public function __construct(
        public int $maxAttributeCount = 64,
        public int $maxAttributeKeyBytes = 256,
        public int $maxAttributeValueBytes = 4096,
        public int $maxAttributeDepth = 8,
        public int $maxOccurrencePayloadBytes = 65536,
        public int $maxSnapshotPayloadBytes = 262144,
        public int $maxActivityDepth = 64,
        public int $maxArtifactsPerExecution = 10000,
        public int $maxSerializationDepth = 64,
        public int $maxSerializationBytes = 1048576,
    ) {
        foreach (
            [
                'maxAttributeCount' => $this->maxAttributeCount,
                'maxAttributeKeyBytes' => $this->maxAttributeKeyBytes,
                'maxAttributeValueBytes' => $this->maxAttributeValueBytes,
                'maxAttributeDepth' => $this->maxAttributeDepth,
                'maxOccurrencePayloadBytes' => $this->maxOccurrencePayloadBytes,
                'maxSnapshotPayloadBytes' => $this->maxSnapshotPayloadBytes,
                'maxActivityDepth' => $this->maxActivityDepth,
                'maxArtifactsPerExecution' => $this->maxArtifactsPerExecution,
                'maxSerializationDepth' => $this->maxSerializationDepth,
                'maxSerializationBytes' => $this->maxSerializationBytes,
            ] as $name => $value
        ) {
            if ($value < 1) {
                throw new InvalidArgumentException(\sprintf('Security limit %s must be a positive integer, got %d', $name, $value));
            }
        }
    }

    /**
     * Returns a copy with one limit changed.
     */
    public function with(string $name, int $value): self
    {
        return new self(
            maxAttributeCount: $name === 'maxAttributeCount' ? $value : $this->maxAttributeCount,
            maxAttributeKeyBytes: $name === 'maxAttributeKeyBytes' ? $value : $this->maxAttributeKeyBytes,
            maxAttributeValueBytes: $name === 'maxAttributeValueBytes' ? $value : $this->maxAttributeValueBytes,
            maxAttributeDepth: $name === 'maxAttributeDepth' ? $value : $this->maxAttributeDepth,
            maxOccurrencePayloadBytes: $name === 'maxOccurrencePayloadBytes' ? $value : $this->maxOccurrencePayloadBytes,
            maxSnapshotPayloadBytes: $name === 'maxSnapshotPayloadBytes' ? $value : $this->maxSnapshotPayloadBytes,
            maxActivityDepth: $name === 'maxActivityDepth' ? $value : $this->maxActivityDepth,
            maxArtifactsPerExecution: $name === 'maxArtifactsPerExecution' ? $value : $this->maxArtifactsPerExecution,
            maxSerializationDepth: $name === 'maxSerializationDepth' ? $value : $this->maxSerializationDepth,
            maxSerializationBytes: $name === 'maxSerializationBytes' ? $value : $this->maxSerializationBytes,
        );
    }
}
