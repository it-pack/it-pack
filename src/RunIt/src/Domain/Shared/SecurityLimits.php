<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Domain\Exception\InvalidSecurityLimitException;

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
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SecurityLimits
{
    /**
     * The default attribute-count bound, shared with
     * {@see \ItPack\RunIt\Domain\Context\ExecutionContext::MAX_ENTRIES} so the two
     * entry ceilings cannot drift apart silently.
     */
    public const int DEFAULT_MAX_ATTRIBUTE_COUNT = 64;

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
     * @param int $maxTrackedExecutions       maximum executions tracked by per-execution caches before oldest entries are evicted
     */
    public function __construct(
        public int $maxAttributeCount = self::DEFAULT_MAX_ATTRIBUTE_COUNT,
        public int $maxAttributeKeyBytes = 256,
        public int $maxAttributeValueBytes = 4096,
        public int $maxAttributeDepth = 8,
        public int $maxOccurrencePayloadBytes = 65536,
        public int $maxSnapshotPayloadBytes = 262144,
        public int $maxActivityDepth = 64,
        public int $maxArtifactsPerExecution = 10000,
        public int $maxSerializationDepth = 64,
        public int $maxSerializationBytes = 1048576,
        public int $maxTrackedExecutions = 10000,
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
                'maxTrackedExecutions' => $this->maxTrackedExecutions,
            ] as $name => $value
        ) {
            if ($value < 1) {
                throw InvalidSecurityLimitException::forNonPositive($name, $value);
            }
        }
    }

    /**
     * Returns a copy of this instance with the given limits overridden.
     *
     * Every limit name matches its constructor parameter, so a single named-argument
     * helper replaces the previously hand-copied per-limit clone methods. This runs at
     * configuration time only, never on a hot path.
     */
    private function with(int ...$overrides): self
    {
        return new self(
            maxAttributeCount: $overrides['maxAttributeCount'] ?? $this->maxAttributeCount,
            maxAttributeKeyBytes: $overrides['maxAttributeKeyBytes'] ?? $this->maxAttributeKeyBytes,
            maxAttributeValueBytes: $overrides['maxAttributeValueBytes'] ?? $this->maxAttributeValueBytes,
            maxAttributeDepth: $overrides['maxAttributeDepth'] ?? $this->maxAttributeDepth,
            maxOccurrencePayloadBytes: $overrides['maxOccurrencePayloadBytes'] ?? $this->maxOccurrencePayloadBytes,
            maxSnapshotPayloadBytes: $overrides['maxSnapshotPayloadBytes'] ?? $this->maxSnapshotPayloadBytes,
            maxActivityDepth: $overrides['maxActivityDepth'] ?? $this->maxActivityDepth,
            maxArtifactsPerExecution: $overrides['maxArtifactsPerExecution'] ?? $this->maxArtifactsPerExecution,
            maxSerializationDepth: $overrides['maxSerializationDepth'] ?? $this->maxSerializationDepth,
            maxSerializationBytes: $overrides['maxSerializationBytes'] ?? $this->maxSerializationBytes,
            maxTrackedExecutions: $overrides['maxTrackedExecutions'] ?? $this->maxTrackedExecutions,
        );
    }

    /**
     * Returns a copy with the maximum attribute count changed.
     */
    public function withMaxAttributeCount(int $maxAttributeCount): self
    {
        return $this->with(maxAttributeCount: $maxAttributeCount);
    }

    /**
     * Returns a copy with the maximum attribute key length changed.
     */
    public function withMaxAttributeKeyBytes(int $maxAttributeKeyBytes): self
    {
        return $this->with(maxAttributeKeyBytes: $maxAttributeKeyBytes);
    }

    /**
     * Returns a copy with the maximum scalar attribute value length changed.
     */
    public function withMaxAttributeValueBytes(int $maxAttributeValueBytes): self
    {
        return $this->with(maxAttributeValueBytes: $maxAttributeValueBytes);
    }

    /**
     * Returns a copy with the maximum attribute nesting depth changed.
     */
    public function withMaxAttributeDepth(int $maxAttributeDepth): self
    {
        return $this->with(maxAttributeDepth: $maxAttributeDepth);
    }

    /**
     * Returns a copy with the maximum occurrence payload size changed.
     */
    public function withMaxOccurrencePayloadBytes(int $maxOccurrencePayloadBytes): self
    {
        return $this->with(maxOccurrencePayloadBytes: $maxOccurrencePayloadBytes);
    }

    /**
     * Returns a copy with the maximum snapshot payload size changed.
     */
    public function withMaxSnapshotPayloadBytes(int $maxSnapshotPayloadBytes): self
    {
        return $this->with(maxSnapshotPayloadBytes: $maxSnapshotPayloadBytes);
    }

    /**
     * Returns a copy with the maximum nested-activity depth changed.
     */
    public function withMaxActivityDepth(int $maxActivityDepth): self
    {
        return $this->with(maxActivityDepth: $maxActivityDepth);
    }

    /**
     * Returns a copy with the maximum artifacts per execution changed.
     */
    public function withMaxArtifactsPerExecution(int $maxArtifactsPerExecution): self
    {
        return $this->with(maxArtifactsPerExecution: $maxArtifactsPerExecution);
    }

    /**
     * Returns a copy with the maximum serialization depth changed.
     */
    public function withMaxSerializationDepth(int $maxSerializationDepth): self
    {
        return $this->with(maxSerializationDepth: $maxSerializationDepth);
    }

    /**
     * Returns a copy with the maximum serialized payload size changed.
     */
    public function withMaxSerializationBytes(int $maxSerializationBytes): self
    {
        return $this->with(maxSerializationBytes: $maxSerializationBytes);
    }

    /**
     * Returns a copy with the maximum tracked-executions cache bound changed.
     */
    public function withMaxTrackedExecutions(int $maxTrackedExecutions): self
    {
        return $this->with(maxTrackedExecutions: $maxTrackedExecutions);
    }
}
