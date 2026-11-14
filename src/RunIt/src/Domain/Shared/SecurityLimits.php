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

    public static function default(): self
    {
        return new self();
    }

    public function withMaxAttributeCount(int $maxAttributeCount): self
    {
        return $this->with(maxAttributeCount: $maxAttributeCount);
    }

    public function withMaxAttributeKeyBytes(int $maxAttributeKeyBytes): self
    {
        return $this->with(maxAttributeKeyBytes: $maxAttributeKeyBytes);
    }

    public function withMaxAttributeValueBytes(int $maxAttributeValueBytes): self
    {
        return $this->with(maxAttributeValueBytes: $maxAttributeValueBytes);
    }

    public function withMaxAttributeDepth(int $maxAttributeDepth): self
    {
        return $this->with(maxAttributeDepth: $maxAttributeDepth);
    }

    public function withMaxOccurrencePayloadBytes(int $maxOccurrencePayloadBytes): self
    {
        return $this->with(maxOccurrencePayloadBytes: $maxOccurrencePayloadBytes);
    }

    public function withMaxSnapshotPayloadBytes(int $maxSnapshotPayloadBytes): self
    {
        return $this->with(maxSnapshotPayloadBytes: $maxSnapshotPayloadBytes);
    }

    public function withMaxActivityDepth(int $maxActivityDepth): self
    {
        return $this->with(maxActivityDepth: $maxActivityDepth);
    }

    public function withMaxArtifactsPerExecution(int $maxArtifactsPerExecution): self
    {
        return $this->with(maxArtifactsPerExecution: $maxArtifactsPerExecution);
    }

    public function withMaxSerializationDepth(int $maxSerializationDepth): self
    {
        return $this->with(maxSerializationDepth: $maxSerializationDepth);
    }

    public function withMaxSerializationBytes(int $maxSerializationBytes): self
    {
        return $this->with(maxSerializationBytes: $maxSerializationBytes);
    }

    public function withMaxTrackedExecutions(int $maxTrackedExecutions): self
    {
        return $this->with(maxTrackedExecutions: $maxTrackedExecutions);
    }

    private function with(int ...$overrides): self
    {
        /** @var array<string, int> $defaults */
        $defaults = [
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
        ];
        $merged = array_replace($defaults, $overrides);

        return new self(
            maxAttributeCount: $merged['maxAttributeCount'],
            maxAttributeKeyBytes: $merged['maxAttributeKeyBytes'],
            maxAttributeValueBytes: $merged['maxAttributeValueBytes'],
            maxAttributeDepth: $merged['maxAttributeDepth'],
            maxOccurrencePayloadBytes: $merged['maxOccurrencePayloadBytes'],
            maxSnapshotPayloadBytes: $merged['maxSnapshotPayloadBytes'],
            maxActivityDepth: $merged['maxActivityDepth'],
            maxArtifactsPerExecution: $merged['maxArtifactsPerExecution'],
            maxSerializationDepth: $merged['maxSerializationDepth'],
            maxSerializationBytes: $merged['maxSerializationBytes'],
            maxTrackedExecutions: $merged['maxTrackedExecutions'],
        );
    }
}
