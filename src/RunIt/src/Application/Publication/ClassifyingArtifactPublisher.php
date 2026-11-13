<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
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
 * string bytes. Budgets are applied across the rest of the projection: depth 16,
 * 1,024 nodes, 4,096 bytes per string, 1,048,576 bytes total, and field counts
 * capped by SecurityLimits for nested maps other than snapshot payloads. Snapshot
 * payloads use the configured snapshot byte and serialization depth limits.
 * Objects, resources, and unknown fields are
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
            'ended_at', 'parent_activity_id', 'sequence', 'attributes', 'throwable', 'duration_ns',
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
        'execution' => [
            'id',
        ],
        'activity' => [
            'id',
            'execution_id',
        ],
        'measurement' => [
            'execution_id',
        ],
        'observation' => [
            'id',
            'execution_id',
        ],
        'occurrence' => [
            'id',
            'execution_id',
        ],
        'snapshot' => [
            'id',
            'execution_id',
        ],
    ];

    /** Required fields for terminal snapshots: payload/content_hash are omitted by default. */
    private const array REQUIRED_FIELDS_TERMINAL_SNAPSHOT = ['id', 'execution_id'];

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
            $this->reportMissingRequiredField('unknown', 'supported artifact kind');
            return;
        }

        $normalized = $artifact->normalize();

        $isTerminalSnapshot = $kind === 'snapshot'
            && isset($normalized[ArtifactField::CLASSIFICATION])
            && $normalized[ArtifactField::CLASSIFICATION] === SnapshotClassification::Terminal->value;

        // Terminal snapshots omit payload and content hash by default: they are
        // unclassified until an explicit opt-in rewrites them as verified exports.
        if ($isTerminalSnapshot) {
            unset($normalized[ArtifactField::PAYLOAD], $normalized[ArtifactField::CONTENT_HASH]);
        }

        $payloadBytes = 0;
        if (!$isTerminalSnapshot
            && $kind === 'snapshot' && array_key_exists(ArtifactField::PAYLOAD, $normalized)
            && !$this->validSnapshotPayload($normalized[ArtifactField::PAYLOAD], 1, $payloadBytes)) {
            $this->reportMissingRequiredField('snapshot', 'unchanged payload and content_hash');
            return;
        }

        $projected = $this->projectObject($normalized, $kind, 0);

        if (!$isTerminalSnapshot && $kind === 'snapshot'
            && (!array_key_exists(ArtifactField::PAYLOAD, $normalized)
                || !array_key_exists(ArtifactField::PAYLOAD, $projected)
                || $normalized[ArtifactField::PAYLOAD] !== $projected[ArtifactField::PAYLOAD]
                || (array_key_exists(ArtifactField::CONTENT_HASH, $normalized)
                    && (!array_key_exists(ArtifactField::CONTENT_HASH, $projected)
                        || $normalized[ArtifactField::CONTENT_HASH] !== $projected[ArtifactField::CONTENT_HASH]))
                || (!array_key_exists(ArtifactField::CONTENT_HASH, $normalized)
                    && !$artifact instanceof RedactedArtifactInterface))) {
            $this->reportMissingRequiredField('snapshot', 'unchanged payload and content_hash');
            return;
        }

        // Verify required fields are present and safely represented.
        $required = $isTerminalSnapshot
            ? self::REQUIRED_FIELDS_TERMINAL_SNAPSHOT
            : self::REQUIRED_FIELDS[$kind];

        foreach ($required as $field) {
            if (!array_key_exists($field, $projected) || $projected[$field] === null) {
                $this->reportMissingRequiredField($kind, $field);
                return;
            }
        }

        $classified = match ($kind) {
            'measurement' => $this->typedMeasurement($projected),
            'occurrence' => $this->typedOccurrence($projected),
            'observation' => $this->typedObservation($projected),
            'activity' => $this->typedActivity($projected),
            'snapshot' => $this->typedSnapshot($projected),
            default => $this->typedExecution($projected),
        };
        if ($artifact instanceof RedactedArtifactInterface && $classified instanceof Observation) {
            $classified = new ClassifiedRedactedObservation($classified);
        }

        if ($artifact instanceof RedactedArtifactInterface && $classified instanceof Occurrence) {
            $classified = new ClassifiedRedactedOccurrence($classified);
        }

        $classified ??= new ClassifiedArtifact($projected, $kind);
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
    private function typedMeasurement(array $fields): ?MeasurementInterface
    {
        if (array_any(
            ['name', 'kind', 'unit', 'value', 'execution_id', 'activity_id', 'timestamp', 'sequence', 'attributes'],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )) {
            return null;
        }

        if (!is_string($fields['name']) || !is_string($fields['kind']) || !is_string($fields['unit'])
            || !is_string($fields['execution_id']) || !is_int($fields['timestamp'])
            || !is_int($fields['sequence']) || !is_array($fields['attributes'])
            || (!is_string($fields['activity_id']) && $fields['activity_id'] !== null)) {
            return null;
        }

        $factory = match ($fields['kind']) {
            'counter' => Measurement::counter(...),
            'size' => Measurement::size(...),
            'gauge' => Measurement::gauge(...),
            'duration' => Measurement::duration(...),
            'ratio' => Measurement::ratio(...),
            default => null,
        };
        $value = $fields['value'];
        $unit = MeasurementUnit::tryFrom($fields['unit']);
        if ($factory === null || $unit === null || (!is_int($value) && !is_float($value))
            || (in_array($fields['kind'], ['counter', 'size'], true) ? !is_int($value) : !is_float($value))) {
            return null;
        }

        $attributes = [];
        foreach ($fields['attributes'] as $key => $attribute) {
            if (!is_string($key) || !(is_scalar($attribute) || is_array($attribute) || $attribute === null)) {
                return null;
            }

            $attributes[$key] = $attribute;
        }

        try {
            $measurement = $factory(
                MeasurementName::fromString($fields['name']),
                ExecutionId::fromString($fields['execution_id']),
                $value,
                $fields['activity_id'] === null ? null : ActivityId::fromString($fields['activity_id']),
                Instant::of($fields['timestamp']),
                ArtifactSequence::of($fields['sequence']),
                AttributeCollection::fromArray($attributes),
                $unit,
            );
            $normalized = $measurement->normalize();
            ksort($normalized);
            ksort($fields);

            return $normalized === $fields ? $measurement : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $fields */
    private function typedObservation(array $fields): ?ObservationInterface
    {
        $keys = [
            'id', 'execution_id', 'activity_id', 'severity', 'level', 'message', 'diagnostic_code', 'sequence',
            'timestamp', 'attributes', 'throwable'];
        if (array_any($keys, fn (string $key): bool => !array_key_exists($key, $fields))) {
            return null;
        }

        if (!is_string($fields['execution_id']) || !is_int($fields['timestamp'])
            || !is_int($fields['sequence']) || !is_array($fields['attributes'])
            || (!is_string($fields['activity_id']) && $fields['activity_id'] !== null)) {
            return null;
        }

        $attributes = [];
        foreach ($fields['attributes'] as $key => $value) {
            if (!is_string($key) || !(is_scalar($value) || is_array($value) || $value === null)) {
                return null;
            }

            $attributes[$key] = $value;
        }

        try {
            $executionId = ExecutionId::fromString($fields['execution_id']);
            $activityId = $fields['activity_id'] === null ? null : ActivityId::fromString($fields['activity_id']);
            $instant = Instant::of($fields['timestamp']);
            $sequence = ArtifactSequence::of($fields['sequence']);
            $attributes = AttributeCollection::fromArray($attributes);
            if (!is_string($fields['id']) || !is_string($fields['message'])
                || (!is_string($fields['diagnostic_code']) && $fields['diagnostic_code'] !== null)
                || !in_array($fields['severity'], ['debug', 'info', 'notice', 'warning', 'error',
                    'critical', 'alert', 'emergency'], true)) {
                return null;
            }

            $factory = match ($fields['severity']) {
                'debug' => Observation::debug(...),
                'info' => Observation::info(...),
                'notice' => Observation::notice(...),
                'warning' => Observation::warning(...),
                'error' => Observation::error(...),
                'critical' => Observation::critical(...),
                'alert' => Observation::alert(...),
                'emergency' => Observation::emergency(...),
            };
            $throwable = $this->typedThrowable($fields['throwable']);
            $artifact = $factory(
                ObservationId::fromString($fields['id']),
                $executionId,
                $fields['message'],
                $activityId,
                $fields['diagnostic_code'],
                $attributes,
                $throwable,
                $instant,
                $sequence,
            );
            $normalized = $artifact->normalize();
            ksort($normalized);
            ksort($fields);

            return ($fields['throwable'] === null
                || $throwable instanceof ThrowableDescriptionInterface)
                && $normalized === $fields ? $artifact : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function typedThrowable(mixed $value): ?ThrowableDescriptionInterface
    {
        if (!is_array($value) || !isset($value['class'], $value['message'], $value['code'])
            || !array_key_exists('previous', $value) || !array_key_exists('trace', $value)
            || !is_string($value['class']) || !is_string($value['message']) || !is_int($value['code'])
            || (!is_string($value['trace']) && $value['trace'] !== null)) {
            return null;
        }

        $previous = $this->typedThrowable($value['previous']);
        if ($value['previous'] !== null && !$previous instanceof ThrowableDescriptionInterface) {
            return null;
        }

        $description = ThrowableDescription::create(
            $value['class'],
            $value['message'],
            $value['code'],
            $previous,
            $value['trace'],
        );
        $normalized = $description->toArray();
        ksort($normalized);
        ksort($value);
        return $normalized === $value ? $description : null;
    }

    /** @param array<string, mixed> $fields */
    private function typedExecution(array $fields): ?ExecutionInterface
    {
        if (array_any(
            [
            'id', 'name', 'kind', 'status', 'started_at', 'ended_at', 'parent_execution_id',
            'linked_execution_ids', 'sequence', 'context', 'result'],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )) {
            return null;
        }

        // Normalization does not necessarily retain the original context classification.
        if ($fields['context'] !== [] || !is_array($fields['linked_execution_ids'])
            || !is_string($fields['id']) || !is_string($fields['name']) || !is_string($fields['kind'])
            || !is_string($fields['status']) || !is_int($fields['started_at']) || !is_int($fields['sequence'])
            || (!is_int($fields['ended_at']) && $fields['ended_at'] !== null)
            || (!is_string($fields['parent_execution_id']) && $fields['parent_execution_id'] !== null)) {
            return null;
        }

        try {
            $status = ExecutionStatus::tryFrom($fields['status']);
            if ($status === null) {
                return null;
            }

            $links = [];
            foreach ($fields['linked_execution_ids'] as $link) {
                if (!is_string($link)) {
                    return null;
                }

                $links[] = ExecutionId::fromString($link);
            }

            $execution = Execution::create(
                ExecutionId::fromString($fields['id']),
                ExecutionDefinition::create(
                    ExecutionName::fromString($fields['name']),
                    ExecutionKind::fromString($fields['kind']),
                    $fields['parent_execution_id'] === null
                        ? null : ExecutionId::fromString($fields['parent_execution_id']),
                    $links
                ),
                Instant::of($fields['started_at']),
                ArtifactSequence::of($fields['sequence']),
            );
            if ($status !== ExecutionStatus::Created) {
                $execution = $execution->beginRunning();
            }

            if ($status->isTerminal()) {
                $result = $fields['result'];
                if (!is_array($result) || !array_key_exists('throwable', $result) || !is_int($fields['ended_at'])) {
                    return null;
                }

                $throwable = $this->typedThrowable($result['throwable']);
                $isSuccessful = $status === ExecutionStatus::Succeeded;
                if ($isSuccessful && $result['throwable'] !== null) {
                    return null;
                }

                if (!$isSuccessful && !$throwable instanceof ThrowableDescriptionInterface) {
                    return null;
                }

                $outcome = $isSuccessful
                    ? ExecutionSuccess::create()
                    : ExecutionFailure::of($status, $throwable);
                $execution = $execution->complete($outcome, Instant::of($fields['ended_at']));
            }

            $normalized = $execution->normalize();
            ksort($normalized);
            ksort($fields);
            return $normalized === $fields ? $execution : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $fields */
    private function typedActivity(array $fields): ?ActivityInterface
    {
        if (array_any(
            [
            'id', 'execution_id', 'name', 'kind', 'status', 'started_at', 'ended_at',
            'parent_activity_id', 'sequence', 'attributes', 'throwable'],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )) {
            return null;
        }

        if (!is_string($fields['id']) || !is_string($fields['execution_id']) || !is_string($fields['name'])
            || !is_string($fields['kind']) || !is_string($fields['status']) || !is_int($fields['started_at'])
            || !is_int($fields['sequence']) || !is_array($fields['attributes'])
            || (!is_int($fields['ended_at']) && $fields['ended_at'] !== null)
            || (array_key_exists('duration_ns', $fields) && !is_int($fields['duration_ns']))
            || (!is_string($fields['parent_activity_id']) && $fields['parent_activity_id'] !== null)) {
            return null;
        }

        $attributes = $this->typedAttributes($fields['attributes']);
        if ($attributes === null) {
            return null;
        }

        try {
            $status = ActivityStatus::tryFrom($fields['status']);
            $throwable = $this->typedThrowable($fields['throwable']);
            $throwableOk = $throwable instanceof ThrowableDescriptionInterface;
            if ($status === null || ($fields['throwable'] !== null && !$throwableOk)) {
                return null;
            }

            $activity = Activity::start(
                ActivityId::fromString($fields['id']),
                ExecutionId::fromString($fields['execution_id']),
                ActivityDefinition::create(
                    ActivityName::fromString($fields['name']),
                    ActivityKind::fromString($fields['kind']),
                    $fields['parent_activity_id'] === null
                        ? null : ActivityId::fromString($fields['parent_activity_id'])
                ),
                Instant::of($fields['started_at']),
                ArtifactSequence::of($fields['sequence']),
                AttributeCollection::fromArray($attributes),
            );
            if ($status !== ActivityStatus::Running) {
                if (!is_int($fields['ended_at'])) {
                    return null;
                }

                $activity = $activity->complete(
                    $status,
                    $throwable,
                    Instant::of($fields['ended_at']),
                    isset($fields['duration_ns']) ? Duration::ofNanoseconds($fields['duration_ns']) : null,
                );
            }

            $normalized = $activity->normalize();
            ksort($normalized);
            ksort($fields);
            return $normalized === $fields ? $activity : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $fields */
    private function typedSnapshot(array $fields): ?SnapshotInterface
    {
        if (array_any(
            [
                'id',
                'activity_id',
                'execution_id',
                'type',
                'classification',
                'schema_version',
                'created_at',
                'content_type',
                'content_hash',
                'predecessor_id',
                'sequence',
                'payload',
                'attributes',
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )) {
            return null;
        }

        if (!is_string($fields['id']) || !is_string($fields['execution_id']) || !is_string($fields['type'])
            || !is_string($fields['classification']) || !is_int($fields['schema_version'])
            || !is_int($fields['created_at']) || !is_string($fields['content_type'])
            || !is_string($fields['content_hash'])
            || !is_int($fields['sequence']) || !is_array($fields['attributes'])
            || (!is_string($fields['activity_id']) && $fields['activity_id'] !== null)
            || (!is_string($fields['predecessor_id']) && $fields['predecessor_id'] !== null)) {
            return null;
        }

        $attributes = $this->typedAttributes($fields['attributes']);
        if ($attributes === null) {
            return null;
        }

        try {
            $classification = SnapshotClassification::tryFrom($fields['classification']);
            if ($classification === null) {
                return null;
            }

            $snapshot = Snapshot::create(
                SnapshotId::fromString($fields['id']),
                ExecutionId::fromString($fields['execution_id']),
                SnapshotType::fromString($fields['type']),
                $fields['schema_version'],
                $fields['payload'],
                // Preserve the supplied hash; the original algorithm is not available here.
                new readonly class ($fields['content_hash']) implements HashCalculatorInterface {
                    public function __construct(private string $hash)
                    {
                    }

                    public function calculate(string $content): string
                    {
                        return $this->hash;
                    }
                },
                $fields['activity_id'] === null ? null : ActivityId::fromString($fields['activity_id']),
                $fields['predecessor_id'] === null ? null : SnapshotId::fromString($fields['predecessor_id']),
                $classification,
                Instant::of($fields['created_at']),
                $fields['content_type'],
                AttributeCollection::fromArray($attributes),
                ArtifactSequence::of($fields['sequence']),
            );
            $normalized = $snapshot->normalize();
            ksort($normalized);
            ksort($fields);
            return $normalized === $fields ? $snapshot : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<mixed> $values
     * @return array<string, array<mixed>|bool|float|int|string|null>|null
     */
    private function typedAttributes(array $values): ?array
    {
        $attributes = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !(is_scalar($value) || is_array($value) || $value === null)) {
                return null;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /** @param array<string, mixed> $fields */
    private function typedOccurrence(array $fields): ?OccurrenceInterface
    {
        if (array_any(
            [
                'id',
                'activity_id',
                'execution_id',
                'type',
                'source',
                'time',
                'data',
                'content_type',
                'extension_attributes',
                'sequence',
                'subject',
                'data_schema',
                'version',
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )
        ) {
            return null;
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

        if ($artifact instanceof RedactedArtifactInterface
            || $artifact instanceof VerifiedArtifactProjection) {
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
        $value = array_replace(array_intersect_key($value, array_flip(self::REQUIRED_FIELDS[$kind])), $value);
        foreach ($value as $key => $val) {
            if (!in_array($key, $allowlist, true)) {
                continue;
            }

            $projected = $kind === 'snapshot' && $key === 'payload'
                ? $val : $this->projectValue($val, $depth + 1);

            $this->totalBytes += strlen($key);
            if ($kind !== 'snapshot' || $key !== 'payload') {
                $this->totalBytes += $this->byteLength($projected);
            }

            $this->nodeCount++;
            if ($this->nodeCount > self::MAX_NODES || $this->totalBytes > self::MAX_TOTAL_BYTES) {
                $this->budgetExhausted = true;
                break;
            }

            $result[$key] = $projected;
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

    private function validSnapshotPayload(mixed $value, int $depth, int &$bytes): bool
    {
        if (is_array($value)) {
            if ($depth > $this->limits->maxSerializationDepth) {
                return false;
            }

            foreach ($value as $key => $member) {
                $bytes += is_string($key) ? strlen($key) : 8;
                if ($bytes > $this->limits->maxSnapshotPayloadBytes
                    || !$this->validSnapshotPayload($member, $depth + 1, $bytes)) {
                    return false;
                }
            }

            return true;
        }

        $size = match (true) {
            is_string($value) => strlen($value),
            is_int($value), is_float($value) && is_finite($value) => 8,
            is_bool($value) => 1,
            $value === null => 0,
            default => -1,
        };
        if ($size < 0) {
            return false;
        }

        $bytes += $size;
        return $bytes <= $this->limits->maxSnapshotPayloadBytes;
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
