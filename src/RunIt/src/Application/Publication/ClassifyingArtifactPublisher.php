<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use RuntimeException;
use Throwable;

/**
 * Publisher that creates immutable classified projections before passing to the
 * inner publisher.
 *
 * The classified projection uses an explicit allowlist by artifact kind: only
 * fields known for that kind are preserved at the top level, all others are
 * dropped. Nested maps (attributes, data, payload, extension_attributes,
 * context, result) keep their keys but are bounded in depth, node count, and
 * string bytes. Budgets are applied across the whole projection: depth 16,
 * 1,024 nodes, 4,096 bytes per string, 1,048,576 bytes total, and field counts
 * capped by SecurityLimits. Objects, resources, and unknown fields are
 * rejected. On exhaustion, optional fields are omitted or replaced, required
 * fields cause the artifact to be dropped with a payload-free health report.
 *
 * This runs after redaction and after sampling (sampling wraps the classifier
 * in the default stack), so even artifacts that pass the deny-list redactor
 * unchanged are projected to a safe, bounded form.
 *
 * @internal classifying publisher; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ClassifyingArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    private const int MAX_DEPTH = 16;

    private const int MAX_NODES = 1024;

    private const int MAX_STRING_BYTES = 4096;

    private const int MAX_TOTAL_BYTES = 1_048_576;

    /**
     * Allowlisted fields per artifact kind, from config/normalization-schema.json.
     * Fields not in the allowlist for the artifact's kind are dropped.
     *
     * @var array<string, list<string>>
     */
    private const array FIELD_ALLOWLIST = [
        'execution' => [
            'id', 'name', 'kind', 'status', 'started_at', 'ended_at',
            'parent_execution_id', 'linked_execution_ids', 'sequence',
            'context', 'result',
        ],
        'execution_definition' => [
            'name', 'kind', 'parent_execution_id', 'linked_execution_ids', 'context',
        ],
        'activity' => [
            'id', 'execution_id', 'name', 'kind', 'status', 'started_at',
            'ended_at', 'parent_activity_id', 'sequence', 'attributes', 'throwable',
        ],
        'activity_definition' => [
            'name', 'kind', 'parent_activity_id',
        ],
        'measurement' => [
            'name', 'kind', 'unit', 'value', 'execution_id', 'activity_id',
            'timestamp', 'sequence', 'attributes',
        ],
        'observation' => [
            'id', 'execution_id', 'activity_id', 'severity', 'level', 'message',
            'diagnostic_code', 'sequence', 'timestamp', 'attributes', 'throwable',
        ],
        'occurrence' => [
            'id', 'execution_id', 'activity_id', 'type', 'version', 'source',
            'subject', 'time', 'data', 'data_schema', 'content_type',
            'extension_attributes', 'sequence',
        ],
        'snapshot' => [
            'id', 'execution_id', 'activity_id', 'type', 'classification',
            'schema_version', 'created_at', 'content_type', 'content_hash',
            'predecessor_id', 'sequence', 'payload', 'attributes',
        ],
    ];

    /**
     * Required fields per artifact kind. If a required field cannot be safely
     * represented, the artifact is dropped with a payload-free report.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED_FIELDS = [
        'execution' => ['id'],
        'activity' => ['id', 'execution_id'],
        'measurement' => ['execution_id'],
        'observation' => ['id', 'execution_id'],
        'occurrence' => ['id', 'execution_id'],
        'snapshot' => ['id', 'execution_id'],
    ];

    /**
     * Artifact kinds supported by classification. Anything else is rejected
     * with a payload-free report rather than published as an empty projection.
     *
     * @var list<string>
     */
    private const array SUPPORTED_KINDS = [
        'execution',
        'activity',
        'measurement',
        'observation',
        'occurrence',
        'snapshot',
    ];

    private int $totalBytes = 0;

    private int $nodeCount = 0;

    private bool $budgetExhausted = false;

    private bool $publishingHealthReport = false;

    public function __construct(
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->totalBytes = 0;
        $this->nodeCount = 0;
        $this->budgetExhausted = false;

        $kind = $this->artifactKind($artifact);

        if (!in_array($kind, self::SUPPORTED_KINDS, true)) {
            $this->reportUnsupportedKind($kind);
            return;
        }

        $normalized = $artifact->normalize();

        $projected = $this->projectObject($normalized, $kind, 0);

        if ($kind === 'snapshot'
            && (!array_key_exists('payload', $normalized)
                || !isset($normalized['content_hash'])
                || !array_key_exists('payload', $projected)
                || !isset($projected['content_hash'])
                || $normalized['payload'] !== $projected['payload']
                || $normalized['content_hash'] !== $projected['content_hash'])) {
            $this->reportMissingRequiredField('snapshot', 'unchanged payload and content_hash');
            return;
        }

        // Verify required fields are present and safely represented.
        $required = self::REQUIRED_FIELDS[$kind];
        foreach ($required as $field) {
            if (!array_key_exists($field, $projected) || $projected[$field] === null) {
                $this->reportMissingRequiredField($kind, $field);
                return;
            }
        }

        $classified = $kind === 'occurrence'
            ? ($this->typedOccurrence($projected) ?? new ClassifiedArtifact($projected, $kind))
            : new ClassifiedArtifact($projected, $kind);
        $this->inner->publish($classified);
    }

    public function flush(): void
    {
        if ($this->inner instanceof FlushablePublisherInterface) {
            $this->inner->flush();
        }
    }

    public function close(): void
    {
        if ($this->inner instanceof CloseablePublisherInterface) {
            $this->inner->close();
        }
    }

    /** @param array<string, mixed> $fields */
    private function typedOccurrence(array $fields): ?OccurrenceInterface
    {
        foreach (['id', 'execution_id', 'type', 'source', 'time', 'data', 'content_type',
            'extension_attributes', 'sequence', 'activity_id', 'subject', 'data_schema', 'version'] as $key) {
            if (!array_key_exists($key, $fields)) {
                return null;
            }
        }

        if (!is_string($fields['id']) || !is_string($fields['execution_id'])
            || !is_string($fields['type']) || !is_string($fields['source'])
            || !is_int($fields['time']) || !is_string($fields['content_type'])
            || !is_array($fields['extension_attributes']) || !is_int($fields['sequence'])
            || (!is_string($fields['activity_id']) && $fields['activity_id'] !== null)
            || (!is_string($fields['subject']) && $fields['subject'] !== null)
            || (!is_string($fields['data_schema']) && $fields['data_schema'] !== null)) {
            return null;
        }

        $extensions = [];
        foreach ($fields['extension_attributes'] as $key => $value) {
            if (!is_string($key) || !(is_scalar($value) || is_array($value) || $value === null)) {
                return null;
            }

            $extensions[$key] = $value;
        }

        try {
            $occurrence = Occurrence::create(
                OccurrenceId::fromString($fields['id']),
                ExecutionId::fromString($fields['execution_id']),
                OccurrenceType::fromString($fields['type']),
                $fields['source'],
                Instant::of($fields['time']),
                $fields['data'],
                $fields['activity_id'] === null ? null : ActivityId::fromString($fields['activity_id']),
                $fields['subject'],
                $fields['data_schema'],
                $fields['content_type'],
                AttributeCollection::fromArray($extensions),
                ArtifactSequence::of($fields['sequence']),
            );
            // Reject defaults or conversions that would invent projected values.
            $normalized = $occurrence->normalize();
            ksort($normalized);
            ksort($fields);

            return $normalized === $fields ? $occurrence : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine the artifact kind from the artifact's concrete type, not from
     * forgeable normalized fields. SDK-created redacted artifacts
     * ({@see RedactedArtifactInterface} produced by {@see DefaultArtifactRedactor})
     * carry a trustworthy artifactType() because it is resolved through instanceof
     * checks in the redactor, not user-controlled fields.
     */
    private function artifactKind(ExecutionArtifactInterface $artifact): string
    {
        if ($artifact instanceof ObservationInterface) {
            return 'observation';
        }

        if ($artifact instanceof ActivityInterface) {
            return 'activity';
        }

        if ($artifact instanceof MeasurementInterface) {
            return 'measurement';
        }

        if ($artifact instanceof OccurrenceInterface) {
            return 'occurrence';
        }

        if ($artifact instanceof ExecutionInterface) {
            return 'execution';
        }

        if ($artifact instanceof SnapshotInterface) {
            return 'snapshot';
        }

        if ($artifact instanceof RedactedArtifactInterface) {
            $type = $artifact->artifactType();
            if (array_key_exists($type, self::FIELD_ALLOWLIST)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Project a top-level object: apply the kind allowlist, then recursively
     * bound nested maps.
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function projectObject(array $value, string $kind, int $depth): array
    {
        if ($this->budgetExhausted || $depth > self::MAX_DEPTH) {
            $this->budgetExhausted = true;

            return [];
        }

        $this->nodeCount++;
        if ($this->nodeCount > self::MAX_NODES) {
            $this->budgetExhausted = true;

            return [];
        }

        $allowlist = self::FIELD_ALLOWLIST[$kind] ?? [];
        $result = [];
        $optionalCount = 0;

        $value = array_replace(array_intersect_key($value, array_flip(self::REQUIRED_FIELDS[$kind])), $value);
        foreach ($value as $key => $val) {
            if (!in_array($key, $allowlist, true)) {
                continue;
            }

            $required = in_array($key, self::REQUIRED_FIELDS[$kind], true);
            if (!$required && $optionalCount >= $this->limits->maxAttributeCount) {
                continue;
            }

            $projected = $this->projectValue($val, $depth + 1);

            $this->totalBytes += strlen($key);
            $this->totalBytes += $this->byteLength($projected);

            $this->nodeCount++;
            if ($this->nodeCount > self::MAX_NODES || $this->totalBytes > self::MAX_TOTAL_BYTES) {
                $this->budgetExhausted = true;
                break;
            }

            $result[$key] = $projected;
            if (!$required) {
                $optionalCount++;
            }
        }

        return $result;
    }

    /**
     * Project a value: scalars are bounded, maps are recursively projected
     * keeping their keys, other types are rejected.
     */
    private function projectValue(mixed $value, int $depth): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $bytes = strlen($value);
            if ($bytes > self::MAX_STRING_BYTES) {
                return mb_strcut($value, 0, self::MAX_STRING_BYTES, 'UTF-8');
            }

            return $value;
        }

        if (is_array($value)) {
            // Nested maps keep their keys but are recursively bounded.
            return $this->projectMap($value, $depth);
        }

        // Objects, resources, and other types are rejected.
        return '[redacted]';
    }

    /**
     * Project a map: keep all keys, recursively bound values, respect budgets.
     *
     * @param array<mixed, mixed> $value
     * @return array<string, mixed>
     */
    private function projectMap(array $value, int $depth): array
    {
        if ($this->budgetExhausted || $depth > self::MAX_DEPTH) {
            $this->budgetExhausted = true;

            return [];
        }

        $this->nodeCount++;
        if ($this->nodeCount > self::MAX_NODES) {
            $this->budgetExhausted = true;

            return [];
        }

        $result = [];

        foreach ($value as $key => $val) {
            if (count($result) >= $this->limits->maxAttributeCount) {
                break;
            }

            $stringKey = is_string($key) ? $key : (string) $key;
            $projected = $this->projectValue($val, $depth + 1);

            $this->totalBytes += strlen($stringKey);
            $this->totalBytes += $this->byteLength($projected);

            $this->nodeCount++;
            if ($this->nodeCount > self::MAX_NODES || $this->totalBytes > self::MAX_TOTAL_BYTES) {
                $this->budgetExhausted = true;
                break;
            }

            $result[$stringKey] = $projected;
        }

        return $result;
    }

    private function reportMissingRequiredField(string $kind, string $field): void
    {
        if (!$this->health instanceof InternalHealthReporterInterface) {
            new FallbackErrorLogReporter()->report(
                new RuntimeException('Required field missing in classified projection: ' . $kind . '.' . $field),
                new RuntimeException('no health reporter configured'),
            );

            return;
        }

        if ($this->publishingHealthReport) {
            return;
        }

        try {
            $this->publishingHealthReport = true;
            $this->health->report(
                new RuntimeException('Required field missing in classified projection: ' . $kind . '.' . $field),
                new ClassifiedArtifact([
                    ArtifactField::ID => 'redacted',
                    ArtifactField::TYPE => 'runit.artifact.redacted',
                ], 'redacted'),
            );
        } catch (Throwable $throwable) {
            // Contain health reporter failures so required-field drops stay fail-open.
            try {
                new FallbackErrorLogReporter()->report(
                    new RuntimeException('Required field missing in classified projection: ' . $kind . '.' . $field),
                    $throwable,
                );
            } catch (Throwable) {
                // No further independent sink remains; preserve the business outcome.
            }
        } finally {
            $this->publishingHealthReport = false;
        }
    }

    private function reportUnsupportedKind(string $kind): void
    {
        if (!$this->health instanceof InternalHealthReporterInterface) {
            new FallbackErrorLogReporter()->report(
                new RuntimeException('Unsupported artifact kind in classified projection: ' . $kind),
                new RuntimeException('no health reporter configured'),
            );

            return;
        }

        if ($this->publishingHealthReport) {
            return;
        }

        try {
            $this->publishingHealthReport = true;
            $this->health->report(
                new RuntimeException('Unsupported artifact kind in classified projection: ' . $kind),
                new ClassifiedArtifact([
                    ArtifactField::ID => 'redacted',
                    ArtifactField::TYPE => 'runit.artifact.redacted',
                ], 'redacted'),
            );
        } catch (Throwable $throwable) {
            // Contain health reporter failures so unsupported-kind drops stay fail-open.
            try {
                new FallbackErrorLogReporter()->report(
                    new RuntimeException('Unsupported artifact kind in classified projection: ' . $kind),
                    $throwable,
                );
            } catch (Throwable) {
                // No further independent sink remains; preserve the business outcome.
            }
        } finally {
            $this->publishingHealthReport = false;
        }
    }

    /**
     * Return the byte length of a projected value for budget tracking.
     */
    private function byteLength(mixed $value): int
    {
        if (is_null($value)) {
            return 0;
        }

        if (is_string($value)) {
            return strlen($value);
        }

        if (is_scalar($value)) {
            return strlen((string) $value);
        }

        return strlen('[' . gettype($value) . ']');
    }
}
