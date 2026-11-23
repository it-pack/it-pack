<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
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
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
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
        ArtifactType::EXECUTION => [
            ArtifactField::ID, ArtifactField::NAME, ArtifactField::KIND, ArtifactField::STATUS,
            ArtifactField::STARTED_AT, ArtifactField::ENDED_AT, ArtifactField::PARENT_EXECUTION_ID,
            ArtifactField::LINKED_EXECUTION_IDS, ArtifactField::SEQUENCE, ArtifactField::CONTEXT,
            ArtifactField::RESULT,
        ],
        'execution_definition' => [
            ArtifactField::NAME, ArtifactField::KIND, ArtifactField::PARENT_EXECUTION_ID,
            ArtifactField::LINKED_EXECUTION_IDS, ArtifactField::CONTEXT,
        ],
        ArtifactType::ACTIVITY => [
            ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::NAME, ArtifactField::KIND,
            ArtifactField::STATUS, ArtifactField::STARTED_AT, ArtifactField::ENDED_AT,
            ArtifactField::PARENT_ACTIVITY_ID, ArtifactField::SEQUENCE, ArtifactField::ATTRIBUTES,
            ArtifactField::THROWABLE, ArtifactField::DURATION_NS,
        ],
        'activity_definition' => [
            ArtifactField::NAME, ArtifactField::KIND, ArtifactField::PARENT_ACTIVITY_ID,
        ],
        ArtifactType::MEASUREMENT => [
            ArtifactField::NAME, ArtifactField::KIND, ArtifactField::UNIT, ArtifactField::VALUE,
            ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID, ArtifactField::TIMESTAMP,
            ArtifactField::SEQUENCE, ArtifactField::ATTRIBUTES,
        ],
        ArtifactType::OBSERVATION => [
            ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
            ArtifactField::SEVERITY, ArtifactField::LEVEL, ArtifactField::MESSAGE,
            ArtifactField::DIAGNOSTIC_CODE, ArtifactField::SEQUENCE, ArtifactField::TIMESTAMP,
            ArtifactField::ATTRIBUTES, ArtifactField::THROWABLE,
        ],
        ArtifactType::OCCURRENCE => [
            ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
            ArtifactField::TYPE, ArtifactField::VERSION, ArtifactField::SOURCE,
            ArtifactField::SUBJECT, ArtifactField::TIME, ArtifactField::DATA, ArtifactField::DATA_SCHEMA,
            ArtifactField::CONTENT_TYPE, ArtifactField::EXTENSION_ATTRIBUTES, ArtifactField::SEQUENCE,
        ],
        ArtifactType::SNAPSHOT => [
            ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
            ArtifactField::TYPE, ArtifactField::CLASSIFICATION, ArtifactField::SCHEMA_VERSION,
            ArtifactField::CREATED_AT, ArtifactField::CONTENT_TYPE, ArtifactField::CONTENT_HASH,
            ArtifactField::PREDECESSOR_ID, ArtifactField::SEQUENCE, ArtifactField::PAYLOAD,
            ArtifactField::ATTRIBUTES,
        ],
    ];

    /**
     * Required fields per artifact kind. If a required field cannot be safely
     * represented, the artifact is dropped with a payload-free report.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED_FIELDS = [
        ArtifactType::EXECUTION => [
            ArtifactField::ID,
        ],
        ArtifactType::ACTIVITY => [
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
        ],
        ArtifactType::MEASUREMENT => [
            ArtifactField::EXECUTION_ID,
        ],
        ArtifactType::OBSERVATION => [
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
        ],
        ArtifactType::OCCURRENCE => [
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
        ],
        ArtifactType::SNAPSHOT => [
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
        ],
    ];

    /**
     * Artifact kinds supported by classification. Anything else is rejected
     * with a payload-free report rather than published as an empty projection.
     *
     * @var list<string>
     */
    private const array SUPPORTED_KINDS = ArtifactType::TYPES;

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
            $this->reportMissingRequiredField(ArtifactType::UNKNOWN, 'supported artifact kind');
            return;
        }

        $normalized = $artifact->normalize();

        $omittedSnapshotPayload = $this->isSnapshotPayloadOmitted($kind, $normalized, $artifact);

        if (!$this->verifySnapshotPayloadUnchanged($kind, $normalized, $omittedSnapshotPayload)) {
            return;
        }

        $projected = $this->projectObject($normalized, $kind, 0);

        if (!$this->structuralFieldsIntact($normalized, $projected, $kind)) {
            $this->reportMissingRequiredField($kind, 'unchanged structural fields');
            return;
        }

        if (!$this->verifySnapshotPayloadAndContentHash(
            $kind,
            $normalized,
            $projected,
            $omittedSnapshotPayload,
            $artifact,
        )) {
            return;
        }

        if (!$this->verifyRequiredFieldsPresent($projected, $kind)) {
            return;
        }

        $classified = $this->classifyArtifact($projected, $kind, $artifact);
        $classified = $this->wrapRedactedClassified($classified, $artifact);

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

    /**
     * @param array<string, mixed> $normalized
     */
    private function isSnapshotPayloadOmitted(
        string $kind,
        array $normalized,
        ExecutionArtifactInterface $artifact,
    ): bool {
        return $kind === ArtifactType::SNAPSHOT
            && !array_key_exists(ArtifactField::PAYLOAD, $normalized)
            && !array_key_exists(ArtifactField::CONTENT_HASH, $normalized)
            && ($artifact instanceof RedactedArtifactInterface || $artifact instanceof VerifiedArtifactProjection);
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function verifySnapshotPayloadUnchanged(string $kind, array $normalized, bool $omittedSnapshotPayload): bool
    {
        $payloadBytes = 0;
        if (!$omittedSnapshotPayload
            && $kind === ArtifactType::SNAPSHOT
            && array_key_exists(ArtifactField::PAYLOAD, $normalized)
            && !$this->validSnapshotPayload($normalized[ArtifactField::PAYLOAD], 1, $payloadBytes)) {
            $this->reportMissingRequiredField(ArtifactType::SNAPSHOT, 'unchanged payload and content_hash');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $projected
     */
    private function verifySnapshotPayloadAndContentHash(
        string $kind,
        array $normalized,
        array $projected,
        bool $omittedSnapshotPayload,
        ExecutionArtifactInterface $artifact,
    ): bool {
        if (!$omittedSnapshotPayload && $kind === ArtifactType::SNAPSHOT
            && (!array_key_exists(ArtifactField::PAYLOAD, $normalized)
                || !array_key_exists(ArtifactField::PAYLOAD, $projected)
                || $normalized[ArtifactField::PAYLOAD] !== $projected[ArtifactField::PAYLOAD]
                || (array_key_exists(ArtifactField::CONTENT_HASH, $normalized)
                    && (!array_key_exists(ArtifactField::CONTENT_HASH, $projected)
                        || $normalized[ArtifactField::CONTENT_HASH] !== $projected[ArtifactField::CONTENT_HASH]))
                || (!array_key_exists(ArtifactField::CONTENT_HASH, $normalized)
                    && !$artifact instanceof RedactedArtifactInterface))) {
            $this->reportMissingRequiredField(ArtifactType::SNAPSHOT, 'unchanged payload and content_hash');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $projected
     */
    private function verifyRequiredFieldsPresent(array $projected, string $kind): bool
    {
        $required = self::REQUIRED_FIELDS[$kind];

        foreach ($required as $field) {
            if (!array_key_exists($field, $projected) || $projected[$field] === null) {
                $this->reportMissingRequiredField($kind, $field);

                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $projected
     */
    private function classifyArtifact(
        array $projected,
        string $kind,
        ExecutionArtifactInterface $artifact,
    ): ExecutionArtifactInterface {
        return match ($kind) {
            ArtifactType::MEASUREMENT => $this->typedMeasurement($projected),
            ArtifactType::OCCURRENCE => $this->typedOccurrence($projected),
            ArtifactType::OBSERVATION => $this->typedObservation(
                $projected,
                $artifact instanceof RedactedArtifactInterface
            ),
            ArtifactType::ACTIVITY => $this->typedActivity($projected),
            ArtifactType::SNAPSHOT => $this->typedSnapshot($projected),
            default => $this->typedExecution($projected),
        }
        ?? new ClassifiedArtifact($projected, $kind);
    }

    private function wrapRedactedClassified(
        ExecutionArtifactInterface $classified,
        ExecutionArtifactInterface $artifact,
    ): ExecutionArtifactInterface {
        if ($artifact instanceof RedactedArtifactInterface && $classified instanceof Observation) {
            $classified = new ClassifiedRedactedObservation($classified);
        }

        if ($artifact instanceof RedactedArtifactInterface && $classified instanceof Occurrence) {
            $classified = new ClassifiedRedactedOccurrence($classified);
        }

        return $classified;
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $projected
     */
    private function structuralFieldsIntact(array $original, array $projected, string $kind): bool
    {
        $field = match ($kind) {
            ArtifactType::OBSERVATION, ArtifactType::ACTIVITY => ArtifactField::THROWABLE,
            ArtifactType::EXECUTION => ArtifactField::RESULT,
            default => null,
        };

        return $field === null || !is_array($original[$field] ?? null)
            || $this->unchangedStructure($original[$field], $projected[$field] ?? null, 0);
    }

    private function unchangedStructure(mixed $original, mixed $projected, int $depth): bool
    {
        if (!is_array($original)) {
            return $original === $projected;
        }

        if ($depth > self::MAX_DEPTH || !is_array($projected) || array_keys($original) !== array_keys($projected)) {
            return false;
        }

        return array_all(
            $original,
            fn (mixed $value, int|string $key): bool => $this->unchangedStructure($value, $projected[$key], $depth + 1),
        );
    }

    /** @param array<string, mixed> $fields */
    private function typedMeasurement(array $fields): ?MeasurementInterface
    {
        if (!$this->measurementFieldsValid($fields)) {
            return null;
        }

        $kind = $fields[ArtifactField::KIND];
        $factory = $this->resolveMeasurementFactory($kind);
        if (!$factory instanceof Closure) {
            return null;
        }

        $unit = $this->resolveMeasurementUnit($fields);
        if (!$unit instanceof MeasurementUnit) {
            return null;
        }

        $components = $this->resolveMeasurementComponents($fields, $kind);
        if ($components === null) {
            return null;
        }

        try {
            $activityId = $this->resolveTypedActivityId($components['identifiers']);

            $measurement = $factory(
                MeasurementName::fromString($components['identifiers'][ArtifactField::NAME]),
                ExecutionId::fromString($components['identifiers'][ArtifactField::EXECUTION_ID]),
                $components[ArtifactField::VALUE],
                $activityId,
                Instant::of($components['identifiers'][ArtifactField::TIMESTAMP]),
                ArtifactSequence::of($components['identifiers'][ArtifactField::SEQUENCE]),
                AttributeCollection::fromArray($components[ArtifactField::ATTRIBUTES]),
                $unit,
            );
            if (!$measurement instanceof MeasurementInterface) {
                return null;
            }

            $normalized = $measurement->normalize();
            ksort($normalized);
            ksort($fields);

            return $normalized === $fields ? $measurement : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{value: int|float|null, attributes: array<string, int|float|string|bool|null|array<int|string, mixed>>, identifiers: array{name: string, execution_id: string, activity_id: string|null, timestamp: int, sequence: int}}|null
     */
    private function resolveMeasurementComponents(array $fields, mixed $kind): ?array
    {
        $value = $this->resolveValidatedMeasurementValue($fields[ArtifactField::VALUE], $kind);
        if ($value === null) {
            return null;
        }

        $attributes = $this->buildMeasurementAttributes($fields[ArtifactField::ATTRIBUTES]);
        if ($attributes === null) {
            return null;
        }

        $identifiers = $this->extractTypedMeasurementIdentifiers($fields);
        if ($identifiers === null) {
            return null;
        }

        return [
            ArtifactField::VALUE => $value,
            ArtifactField::ATTRIBUTES => $attributes,
            'identifiers' => $identifiers,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function measurementFieldsValid(array $fields): bool
    {
        return $this->measurementRequiredKeysPresent($fields)
            && $this->measurementFieldTypesValid($fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function measurementRequiredKeysPresent(array $fields): bool
    {
        $required = [
            ArtifactField::NAME,
            ArtifactField::KIND,
            ArtifactField::UNIT,
            ArtifactField::VALUE,
            ArtifactField::EXECUTION_ID,
            ArtifactField::ACTIVITY_ID,
            ArtifactField::TIMESTAMP,
            ArtifactField::SEQUENCE,
            ArtifactField::ATTRIBUTES,
        ];

        return !array_any($required, fn (string $key): bool => !array_key_exists($key, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function measurementFieldTypesValid(array $fields): bool
    {
        return is_string($fields[ArtifactField::NAME])
            && is_string($fields[ArtifactField::KIND])
            && is_string($fields[ArtifactField::UNIT])
            && is_string($fields[ArtifactField::EXECUTION_ID])
            && is_int($fields[ArtifactField::TIMESTAMP])
            && is_int($fields[ArtifactField::SEQUENCE])
            && is_array($fields[ArtifactField::ATTRIBUTES])
            && (
                is_string($fields[ArtifactField::ACTIVITY_ID])
                || $fields[ArtifactField::ACTIVITY_ID] === null
            );
    }

    private function resolveMeasurementFactory(mixed $kind): ?Closure
    {
        if (!is_string($kind)) {
            return null;
        }

        return match ($kind) {
            MeasurementKind::Counter->value => Measurement::counter(...),
            MeasurementKind::Size->value => Measurement::size(...),
            MeasurementKind::Gauge->value => Measurement::gauge(...),
            MeasurementKind::Duration->value => Measurement::duration(...),
            MeasurementKind::Ratio->value => Measurement::ratio(...),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveMeasurementUnit(array $fields): ?MeasurementUnit
    {
        $unitValue = $fields[ArtifactField::UNIT];
        if (!is_string($unitValue)) {
            return null;
        }

        return MeasurementUnit::tryFrom($unitValue);
    }

    private function resolveValidatedMeasurementValue(mixed $value, mixed $kind): int|float|null
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        if (!is_string($kind)) {
            return null;
        }

        if (in_array($kind, ['counter', 'size'], true)) {
            return is_int($value) ? $value : null;
        }

        return is_float($value) ? $value : null;
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, mixed>>|null
     */
    private function buildMeasurementAttributes(mixed $attributes): ?array
    {
        if (!is_array($attributes)) {
            return null;
        }

        $result = [];
        foreach ($attributes as $key => $attribute) {
            if (!is_string($key) || !(is_scalar($attribute) || is_array($attribute) || $attribute === null)) {
                return null;
            }

            $result[$key] = $attribute;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{name: string, execution_id: string, activity_id: string|null, timestamp: int, sequence: int}|null
     */
    private function extractTypedMeasurementIdentifiers(array $fields): ?array
    {
        $name = $fields[ArtifactField::NAME];
        if (!is_string($name)) {
            return null;
        }

        $executionId = $fields[ArtifactField::EXECUTION_ID];
        if (!is_string($executionId)) {
            return null;
        }

        $activityIdValue = $fields[ArtifactField::ACTIVITY_ID];
        if ($activityIdValue !== null && !is_string($activityIdValue)) {
            return null;
        }

        $timestamp = $fields[ArtifactField::TIMESTAMP];
        if (!is_int($timestamp)) {
            return null;
        }

        $sequence = $fields[ArtifactField::SEQUENCE];
        if (!is_int($sequence)) {
            return null;
        }

        return [
            ArtifactField::NAME => $name,
            ArtifactField::EXECUTION_ID => $executionId,
            ArtifactField::ACTIVITY_ID => $activityIdValue,
            ArtifactField::TIMESTAMP => $timestamp,
            ArtifactField::SEQUENCE => $sequence,
        ];
    }

    /** @param array<string, mixed> $fields */
    private function typedObservation(array $fields, bool $redacted): ?ObservationInterface
    {
        if (!$this->observationFieldsValid($fields)) {
            return null;
        }

        $attributes = $this->buildMeasurementAttributes($fields[ArtifactField::ATTRIBUTES]);
        if ($attributes === null) {
            return null;
        }

        $identifiers = $this->extractTypedObservationIdentifiers($fields);
        if ($identifiers === null) {
            return null;
        }

        $trace = $this->prepareTraceObservation($fields, $redacted);

        $severity = $fields[ArtifactField::SEVERITY];
        $factory = $this->resolveObservationFactory($severity);
        if (!$factory instanceof Closure) {
            return null;
        }

        $throwable = $this->typedThrowable($fields[ArtifactField::THROWABLE]);
        if (!$this->observationThrowableValid($fields[ArtifactField::THROWABLE], $throwable)) {
            return null;
        }

        return $this->buildObservationArtifact(
            $fields,
            $identifiers,
            $attributes,
            $throwable,
            $trace,
            $factory,
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @param array{id: string, execution_id: string, activity_id: string|null, message: string, diagnostic_code: string|null, timestamp: int, sequence: int} $identifiers
     * @param array<string, array<int|string, mixed>|bool|float|int|string|null> $attributes
     */
    private function buildObservationArtifact(
        array $fields,
        array $identifiers,
        array $attributes,
        ?ThrowableDescriptionInterface $throwable,
        bool $trace,
        Closure $factory,
    ): ?ObservationInterface {
        try {
            $artifact = $factory(
                ObservationId::fromString($identifiers[ArtifactField::ID]),
                ExecutionId::fromString($identifiers[ArtifactField::EXECUTION_ID]),
                $identifiers[ArtifactField::MESSAGE],
                $this->resolveTypedActivityId($identifiers),
                $identifiers[ArtifactField::DIAGNOSTIC_CODE],
                AttributeCollection::fromArray($attributes),
                $throwable,
                Instant::of($identifiers[ArtifactField::TIMESTAMP]),
                ArtifactSequence::of($identifiers[ArtifactField::SEQUENCE]),
            );
            if (!$artifact instanceof Observation) {
                return null;
            }

            $normalized = $artifact->normalize();
            ksort($normalized);
            ksort($fields);

            if ($normalized !== $fields) {
                return null;
            }

            return $trace ? new ClassifiedTraceObservation($artifact) : $artifact;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $identifiers
     */
    private function resolveTypedActivityId(array $identifiers): ?ActivityId
    {

        $activityIdValue = $identifiers[ArtifactField::ACTIVITY_ID];

        return is_string($activityIdValue)
            ? ActivityId::fromString($activityIdValue)
            : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function observationFieldsValid(array $fields): bool
    {
        return $this->observationRequiredKeysPresent($fields)
            && $this->observationFieldTypesValid($fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function observationRequiredKeysPresent(array $fields): bool
    {
        $keys = [
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
            ArtifactField::ACTIVITY_ID,
            ArtifactField::SEVERITY,
            ArtifactField::LEVEL,
            ArtifactField::MESSAGE,
            ArtifactField::DIAGNOSTIC_CODE,
            ArtifactField::SEQUENCE,
            ArtifactField::TIMESTAMP,
            ArtifactField::ATTRIBUTES,
            ArtifactField::THROWABLE,
        ];

        return !array_any($keys, fn (string $key): bool => !array_key_exists($key, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function observationFieldTypesValid(array $fields): bool
    {
        return is_string($fields[ArtifactField::EXECUTION_ID])
            && is_int($fields[ArtifactField::TIMESTAMP])
            && is_int($fields[ArtifactField::SEQUENCE])
            && is_array($fields[ArtifactField::ATTRIBUTES])
            && (is_string($fields[ArtifactField::ACTIVITY_ID])
                || $fields[ArtifactField::ACTIVITY_ID] === null)
            && is_string($fields[ArtifactField::ID])
            && is_string($fields[ArtifactField::MESSAGE])
            && (is_string($fields[ArtifactField::DIAGNOSTIC_CODE])
                || $fields[ArtifactField::DIAGNOSTIC_CODE] === null);
    }

    private function resolveObservationFactory(mixed $severity): ?Closure
    {
        if (!is_string($severity)) {
            return null;
        }

        return $this->observationFactoryBySeverity($severity);
    }

    private function observationFactoryBySeverity(string $severity): ?Closure
    {
        $factories = [
            ObservationSeverity::Debug->value => Observation::debug(...),
            ObservationSeverity::Info->value => Observation::info(...),
            ObservationSeverity::Notice->value => Observation::notice(...),
            ObservationSeverity::Warning->value => Observation::warning(...),
            ObservationSeverity::Error->value => Observation::error(...),
            ObservationSeverity::Critical->value => Observation::critical(...),
            ObservationSeverity::Alert->value => Observation::alert(...),
            ObservationSeverity::Emergency->value => Observation::emergency(...),
        ];

        return $factories[$severity] ?? null;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{id: string, execution_id: string, activity_id: string|null, message: string, diagnostic_code: string|null, timestamp: int, sequence: int}|null
     */
    private function extractTypedObservationIdentifiers(array $fields): ?array
    {
        $id = $fields[ArtifactField::ID];
        if (!is_string($id)) {
            return null;
        }

        $executionId = $fields[ArtifactField::EXECUTION_ID];
        if (!is_string($executionId)) {
            return null;
        }

        $activityIdValue = $fields[ArtifactField::ACTIVITY_ID];
        if ($activityIdValue !== null && !is_string($activityIdValue)) {
            return null;
        }

        $message = $fields[ArtifactField::MESSAGE];
        if (!is_string($message)) {
            return null;
        }

        $diagnosticCode = $fields[ArtifactField::DIAGNOSTIC_CODE];
        if ($diagnosticCode !== null && !is_string($diagnosticCode)) {
            return null;
        }

        $timestamp = $fields[ArtifactField::TIMESTAMP];
        if (!is_int($timestamp)) {
            return null;
        }

        $sequence = $fields[ArtifactField::SEQUENCE];
        if (!is_int($sequence)) {
            return null;
        }

        return [
            ArtifactField::ID => $id,
            ArtifactField::EXECUTION_ID => $executionId,
            ArtifactField::ACTIVITY_ID => $activityIdValue,
            ArtifactField::MESSAGE => $message,
            ArtifactField::DIAGNOSTIC_CODE => $diagnosticCode,
            ArtifactField::TIMESTAMP => $timestamp,
            ArtifactField::SEQUENCE => $sequence,
        ];
    }

    private function observationThrowableValid(mixed $throwableValue, ?ThrowableDescriptionInterface $throwable): bool
    {
        return $throwableValue === null || $throwable instanceof ThrowableDescriptionInterface;
    }

    /** @param array<string, mixed> $fields */
    private function prepareTraceObservation(array &$fields, bool $redacted): bool
    {
        if (!$redacted || $fields[ArtifactField::SEVERITY] !== ObservationSeverity::Trace->value
            || $fields[ArtifactField::LEVEL] !== ObservationSeverity::Trace->level()) {
            return false;
        }

        $fields[ArtifactField::SEVERITY] = ObservationSeverity::Debug->value;
        $fields[ArtifactField::LEVEL] = ObservationSeverity::Debug->level();
        return true;
    }

    private function typedThrowable(mixed $value): ?ThrowableDescriptionInterface
    {
        if (
            !is_array($value)
            || !isset(
                $value[ArtifactField::THROWABLE_CLASS],
                $value[ArtifactField::MESSAGE],
                $value[ArtifactField::CODE],
            )
            || !array_key_exists(ArtifactField::PREVIOUS, $value)
            || !array_key_exists(ArtifactField::TRACE, $value)
            || !is_string($value[ArtifactField::THROWABLE_CLASS])
            || !is_string($value[ArtifactField::MESSAGE])
            || !is_int($value[ArtifactField::CODE])
            || (!is_string($value[ArtifactField::TRACE]) && $value[ArtifactField::TRACE] !== null)
        ) {
            return null;
        }

        $previous = $this->typedThrowable($value[ArtifactField::PREVIOUS]);
        if ($value[ArtifactField::PREVIOUS] !== null && !$previous instanceof ThrowableDescriptionInterface) {
            return null;
        }

        $description = ThrowableDescription::create(
            $value[ArtifactField::THROWABLE_CLASS],
            $value[ArtifactField::MESSAGE],
            $value[ArtifactField::CODE],
            $previous,
            $value[ArtifactField::TRACE],
        );
        $normalized = $description->toArray();
        ksort($normalized);
        ksort($value);
        return $normalized === $value ? $description : null;
    }

    /** @param array<string, mixed> $fields */
    private function typedExecution(array $fields): ?ExecutionInterface
    {
        if ($this->executionRequiredKeysMissing($fields)) {
            return null;
        }

        if ($this->executionFieldTypesInvalid($fields)) {
            return null;
        }

        /** @var array{id: string, name: string, kind: string, status: string, started_at: int, sequence: int, parent_execution_id: string|null, linked_execution_ids: array<int|string, mixed>, result: mixed, context: array<mixed>, ended_at: int|null} $fields */

        return $this->buildTypedExecution($fields);
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any required execution key is absent
     */
    private function executionRequiredKeysMissing(array $fields): bool
    {
        return array_any(
            [
                ArtifactField::ID,
                ArtifactField::NAME,
                ArtifactField::KIND,
                ArtifactField::STATUS,
                ArtifactField::STARTED_AT,
                ArtifactField::ENDED_AT,
                ArtifactField::PARENT_EXECUTION_ID,
                ArtifactField::LINKED_EXECUTION_IDS,
                ArtifactField::SEQUENCE,
                ArtifactField::CONTEXT,
                ArtifactField::RESULT,
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any execution field type is invalid
     */
    private function executionFieldTypesInvalid(array $fields): bool
    {
        return $fields[ArtifactField::CONTEXT] !== []
            || !is_array($fields[ArtifactField::LINKED_EXECUTION_IDS])
            || !is_string($fields[ArtifactField::ID])
            || !is_string($fields[ArtifactField::NAME])
            || !is_string($fields[ArtifactField::KIND])
            || !is_string($fields[ArtifactField::STATUS])
            || !is_int($fields[ArtifactField::STARTED_AT])
            || !is_int($fields[ArtifactField::SEQUENCE])
            || (!is_int($fields[ArtifactField::ENDED_AT]) && $fields[ArtifactField::ENDED_AT] !== null)
            || (!is_string($fields[ArtifactField::PARENT_EXECUTION_ID])
                && $fields[ArtifactField::PARENT_EXECUTION_ID] !== null);
    }

    /**
     * @param array{id: string, name: string, kind: string, status: string, started_at: int, sequence: int, parent_execution_id: string|null, linked_execution_ids: array<int|string, mixed>, result: mixed, context: array<mixed>, ended_at: int|null} $fields
     */
    private function buildTypedExecution(array $fields): ?ExecutionInterface
    {
        try {
            $status = ExecutionStatus::tryFrom($fields[ArtifactField::STATUS]);
            if ($status === null) {
                return null;
            }

            $links = $this->buildExecutionLinks($fields[ArtifactField::LINKED_EXECUTION_IDS]);
            if ($links === null) {
                return null;
            }

            $execution = Execution::create(
                ExecutionId::fromString($fields[ArtifactField::ID]),
                ExecutionDefinition::create(
                    ExecutionName::fromString($fields[ArtifactField::NAME]),
                    ExecutionKind::fromString($fields[ArtifactField::KIND]),
                    $this->resolveParentExecutionId($fields[ArtifactField::PARENT_EXECUTION_ID]),
                    $links
                ),
                Instant::of($fields[ArtifactField::STARTED_AT]),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
            );
            if ($status !== ExecutionStatus::Created) {
                $execution = $execution->beginRunning();
            }

            $execution = $this->completeTerminalExecution(
                $execution,
                $status,
                $fields[ArtifactField::RESULT],
                $fields[ArtifactField::ENDED_AT],
            );
            if (!$execution instanceof Execution) {
                return null;
            }

            $normalized = $execution->normalize();
            ksort($normalized);
            ksort($fields);
            if ($normalized !== $fields) {
                return null;
            }

            return $execution;
        } catch (Throwable) {
            return null;
        }
    }

    private function completeTerminalExecution(
        Execution $execution,
        ExecutionStatus $status,
        mixed $result,
        ?int $endedAt,
    ): ?Execution {
        if (!$status->isTerminal()) {
            return $execution;
        }

        if (!is_array($result) || !array_key_exists(ArtifactField::THROWABLE, $result)) {
            return null;
        }

        $throwable = $this->typedThrowable($result[ArtifactField::THROWABLE]);
        $isSuccessful = $status === ExecutionStatus::Succeeded;
        if ($isSuccessful && $result[ArtifactField::THROWABLE] !== null) {
            return null;
        }

        if (!$isSuccessful && !$throwable instanceof ThrowableDescriptionInterface) {
            return null;
        }

        $outcome = $isSuccessful
            ? ExecutionSuccess::create()
            : ExecutionFailure::of($status, $throwable);

        if ($endedAt === null) {
            return null;
        }

        return $execution->complete($outcome, Instant::of($endedAt));
    }

    /**
     * @param array<int|string, mixed> $linkedIds
     * @return list<ExecutionId>|null
     */
    private function buildExecutionLinks(array $linkedIds): ?array
    {
        $links = [];
        foreach ($linkedIds as $link) {
            if (!is_string($link)) {
                return null;
            }

            $links[] = ExecutionId::fromString($link);
        }

        return $links;
    }

    private function resolveParentExecutionId(mixed $parentId): ?ExecutionId
    {
        return !is_string($parentId)
            ? null
            : ExecutionId::fromString($parentId);
    }

    /** @param array<string, mixed> $fields */
    private function typedActivity(array $fields): ?ActivityInterface
    {
        if ($this->activityRequiredKeysMissing($fields)) {
            return null;
        }

        if ($this->activityFieldTypesInvalid($fields)) {
            return null;
        }

        /** @var array{
         *     id: string,
         *     execution_id: string,
         *     name: string,
         *     kind: string,
         *     status: string,
         *     started_at: int,
         *     sequence: int,
         *     parent_activity_id: string|null,
         *     attributes: array<mixed>,
         *     throwable: mixed,
         *     ended_at: int|null,
         *     duration_ns?: int
         * } $fields
         */

        $attributes = $this->typedAttributes($fields[ArtifactField::ATTRIBUTES]);
        if ($attributes === null) {
            return null;
        }

        return $this->buildTypedActivity($fields, $attributes);
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any required activity key is absent
     */
    private function activityRequiredKeysMissing(array $fields): bool
    {
        return array_any(
            [
                ArtifactField::ID,
                ArtifactField::EXECUTION_ID,
                ArtifactField::NAME,
                ArtifactField::KIND,
                ArtifactField::STATUS,
                ArtifactField::STARTED_AT,
                ArtifactField::ENDED_AT,
                ArtifactField::PARENT_ACTIVITY_ID,
                ArtifactField::SEQUENCE,
                ArtifactField::ATTRIBUTES,
                ArtifactField::THROWABLE,
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any activity field type is invalid
     */
    private function activityFieldTypesInvalid(array $fields): bool
    {
        return !is_string($fields[ArtifactField::ID])
            || !is_string($fields[ArtifactField::EXECUTION_ID])
            || !is_string($fields[ArtifactField::NAME])
            || !is_string($fields[ArtifactField::KIND])
            || !is_string($fields[ArtifactField::STATUS])
            || !is_int($fields[ArtifactField::STARTED_AT])
            || !is_int($fields[ArtifactField::SEQUENCE])
            || !is_array($fields[ArtifactField::ATTRIBUTES])
            || (!is_int($fields[ArtifactField::ENDED_AT]) && $fields[ArtifactField::ENDED_AT] !== null)
            || (array_key_exists(ArtifactField::DURATION_NS, $fields)
                && !is_int($fields[ArtifactField::DURATION_NS]))
            || (!is_string($fields[ArtifactField::PARENT_ACTIVITY_ID])
                && $fields[ArtifactField::PARENT_ACTIVITY_ID] !== null);
    }

    /**
     * @param array{id: string, execution_id: string, name: string, kind: string, status: string, started_at: int, sequence: int, parent_activity_id: string|null, attributes: array<mixed>, throwable: mixed, ended_at: int|null, duration_ns?: int} $fields
     * @param array<string, array<int|string, mixed>|bool|float|int|string|null> $attributes
     */
    private function buildTypedActivity(array $fields, array $attributes): ?ActivityInterface
    {
        try {
            $status = ActivityStatus::tryFrom($fields[ArtifactField::STATUS]);
            $throwable = $this->typedThrowable($fields[ArtifactField::THROWABLE]);
            $throwableOk = $throwable instanceof ThrowableDescriptionInterface;
            if ($status === null || ($fields[ArtifactField::THROWABLE] !== null && !$throwableOk)) {
                return null;
            }

            $activity = Activity::start(
                ActivityId::fromString($fields[ArtifactField::ID]),
                ExecutionId::fromString($fields[ArtifactField::EXECUTION_ID]),
                ActivityDefinition::create(
                    ActivityName::fromString($fields[ArtifactField::NAME]),
                    ActivityKind::fromString($fields[ArtifactField::KIND]),
                    $fields[ArtifactField::PARENT_ACTIVITY_ID] === null
                        ? null : ActivityId::fromString($fields[ArtifactField::PARENT_ACTIVITY_ID])
                ),
                Instant::of($fields[ArtifactField::STARTED_AT]),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
                AttributeCollection::fromArray($attributes),
            );
            $activity = $this->completeTypedActivity($activity, $status, $throwable, $fields);
            if (!$activity instanceof Activity) {
                return null;
            }

            $normalized = $activity->normalize();
            ksort($normalized);
            ksort($fields);
            return $normalized === $fields ? $activity : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function completeTypedActivity(
        Activity $activity,
        ActivityStatus $status,
        ?ThrowableDescriptionInterface $throwable,
        array $fields,
    ): ?Activity {
        if ($status !== ActivityStatus::Running) {
            $endedAt = $fields[ArtifactField::ENDED_AT];
            if (!is_int($endedAt)) {
                return null;
            }

            $durationNs = null;
            if (array_key_exists(ArtifactField::DURATION_NS, $fields)) {
                $durationNsValue = $fields[ArtifactField::DURATION_NS];
                if (!is_int($durationNsValue)) {
                    return null;
                }

                $durationNs = Duration::ofNanoseconds($durationNsValue);
            }

            return $activity->complete(
                $status,
                $throwable,
                Instant::of($endedAt),
                $durationNs,
            );
        }

        return $activity;
    }

    /** @param array<string, mixed> $fields */
    private function typedSnapshot(array $fields): ?SnapshotInterface
    {
        if ($this->snapshotRequiredKeysMissing($fields)) {
            return null;
        }

        if ($this->snapshotFieldTypesInvalid($fields)) {
            return null;
        }

        /** @var array{id: string, execution_id: string, type: string, classification: string, schema_version: int, created_at: int, content_type: string, content_hash: string, sequence: int, attributes: array<mixed>, activity_id: string|null, predecessor_id: string|null, payload: mixed} $fields */

        $attributes = $this->typedAttributes($fields[ArtifactField::ATTRIBUTES]);
        if ($attributes === null) {
            return null;
        }

        return $this->buildTypedSnapshot($fields, $attributes);
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any required snapshot key is absent
     */
    private function snapshotRequiredKeysMissing(array $fields): bool
    {
        return array_any(
            [
                ArtifactField::ID,
                ArtifactField::ACTIVITY_ID,
                ArtifactField::EXECUTION_ID,
                ArtifactField::TYPE,
                ArtifactField::CLASSIFICATION,
                ArtifactField::SCHEMA_VERSION,
                ArtifactField::CREATED_AT,
                ArtifactField::CONTENT_TYPE,
                ArtifactField::PREDECESSOR_ID,
                ArtifactField::SEQUENCE,
                ArtifactField::PAYLOAD,
                ArtifactField::ATTRIBUTES,
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return bool true when any snapshot field type is invalid
     */
    private function snapshotFieldTypesInvalid(array $fields): bool
    {
        return !is_string($fields[ArtifactField::ID])
            || !is_string($fields[ArtifactField::EXECUTION_ID])
            || !is_string($fields[ArtifactField::TYPE])
            || !is_string($fields[ArtifactField::CLASSIFICATION])
            || !is_int($fields[ArtifactField::SCHEMA_VERSION])
            || !is_int($fields[ArtifactField::CREATED_AT])
            || !is_string($fields[ArtifactField::CONTENT_TYPE])
            || (array_key_exists(ArtifactField::CONTENT_HASH, $fields)
                && !is_string($fields[ArtifactField::CONTENT_HASH]))
            || !is_int($fields[ArtifactField::SEQUENCE])
            || !is_array($fields[ArtifactField::ATTRIBUTES])
            || (!is_string($fields[ArtifactField::ACTIVITY_ID]) && $fields[ArtifactField::ACTIVITY_ID] !== null)
            || (!is_string($fields[ArtifactField::PREDECESSOR_ID]) && $fields[ArtifactField::PREDECESSOR_ID] !== null);
    }

    /**
     * @param array{id: string, execution_id: string, type: string, classification: string, schema_version: int, created_at: int, content_type: string, content_hash?: string, sequence: int, attributes: array<mixed>, activity_id: string|null, predecessor_id: string|null, payload: mixed} $fields
     * @param array<string, array<int|string, mixed>|bool|float|int|string|null> $attributes
     */
    private function buildTypedSnapshot(array $fields, array $attributes): ?SnapshotInterface
    {
        try {
            $classification = SnapshotClassification::tryFrom($fields[ArtifactField::CLASSIFICATION]);
            if ($classification === null) {
                return null;
            }

            $contentHash = $fields[ArtifactField::CONTENT_HASH] ?? '';
            $hashCalculator = $contentHash !== ''
                ? new readonly class ($contentHash) implements HashCalculatorInterface {
                    public function __construct(private string $hash)
                    {
                    }

                    public function calculate(string $content): string
                    {
                        return $this->hash;
                    }
                }
            : new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha256', $content);
                }
            };

            $snapshot = Snapshot::create(
                SnapshotId::fromString($fields[ArtifactField::ID]),
                ExecutionId::fromString($fields[ArtifactField::EXECUTION_ID]),
                SnapshotType::fromString($fields[ArtifactField::TYPE]),
                $fields[ArtifactField::SCHEMA_VERSION],
                $fields[ArtifactField::PAYLOAD],
                $hashCalculator,
                $fields[ArtifactField::ACTIVITY_ID] === null
                    ? null
                    : ActivityId::fromString($fields[ArtifactField::ACTIVITY_ID]),
                $fields[ArtifactField::PREDECESSOR_ID] === null
                    ? null
                    : SnapshotId::fromString($fields[ArtifactField::PREDECESSOR_ID]),
                $classification,
                Instant::of($fields[ArtifactField::CREATED_AT]),
                $fields[ArtifactField::CONTENT_TYPE],
                AttributeCollection::fromArray($attributes),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
            );
            $normalized = $snapshot->normalize();
            ksort($normalized);
            ksort($fields);
            // content_hash may differ when the payload was redacted; exclude it
            // from the structural integrity comparison.
            unset($normalized[ArtifactField::CONTENT_HASH], $fields[ArtifactField::CONTENT_HASH]);
            return $normalized === $fields ? $snapshot : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<mixed> $values
     * @return array<string, array<int|string, mixed>|bool|float|int|string|null>|null
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
                ArtifactField::ID,
                ArtifactField::ACTIVITY_ID,
                ArtifactField::EXECUTION_ID,
                ArtifactField::TYPE,
                ArtifactField::SOURCE,
                ArtifactField::TIME,
                ArtifactField::DATA,
                ArtifactField::CONTENT_TYPE,
                ArtifactField::EXTENSION_ATTRIBUTES,
                ArtifactField::SEQUENCE,
                ArtifactField::SUBJECT,
                ArtifactField::DATA_SCHEMA,
                ArtifactField::VERSION,
            ],
            fn (string $key): bool => !array_key_exists($key, $fields)
        )
        ) {
            return null;
        }

        if (!is_string($fields[ArtifactField::ID]) || !is_string($fields[ArtifactField::EXECUTION_ID])
            || !is_string($fields[ArtifactField::TYPE]) || !is_string($fields[ArtifactField::SOURCE])
            || !is_int($fields[ArtifactField::TIME]) || !is_string($fields[ArtifactField::CONTENT_TYPE])
            || !is_array($fields[ArtifactField::EXTENSION_ATTRIBUTES]) || !is_int($fields[ArtifactField::SEQUENCE])
            || (!is_string($fields[ArtifactField::ACTIVITY_ID]) && $fields[ArtifactField::ACTIVITY_ID] !== null)
            || (!is_string($fields[ArtifactField::SUBJECT]) && $fields[ArtifactField::SUBJECT] !== null)
            || (!is_string($fields[ArtifactField::DATA_SCHEMA]) && $fields[ArtifactField::DATA_SCHEMA] !== null)) {
            return null;
        }

        $extensions = [];
        foreach ($fields[ArtifactField::EXTENSION_ATTRIBUTES] as $key => $value) {
            if (!is_string($key) || !(is_scalar($value) || is_array($value) || $value === null)) {
                return null;
            }

            $extensions[$key] = $value;
        }

        try {
            $occurrence = Occurrence::create(
                OccurrenceId::fromString($fields[ArtifactField::ID]),
                ExecutionId::fromString($fields[ArtifactField::EXECUTION_ID]),
                OccurrenceType::fromString($fields[ArtifactField::TYPE]),
                $fields[ArtifactField::SOURCE],
                Instant::of($fields[ArtifactField::TIME]),
                $fields[ArtifactField::DATA],
                $fields[ArtifactField::ACTIVITY_ID] === null
                    ? null
                    : ActivityId::fromString($fields[ArtifactField::ACTIVITY_ID]),
                $fields[ArtifactField::SUBJECT],
                $fields[ArtifactField::DATA_SCHEMA],
                $fields[ArtifactField::CONTENT_TYPE],
                AttributeCollection::fromArray($extensions),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
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
            return ArtifactType::OBSERVATION;
        }

        if ($artifact instanceof ActivityInterface) {
            return ArtifactType::ACTIVITY;
        }

        if ($artifact instanceof MeasurementInterface) {
            return ArtifactType::MEASUREMENT;
        }

        if ($artifact instanceof OccurrenceInterface) {
            return ArtifactType::OCCURRENCE;
        }

        if ($artifact instanceof ExecutionInterface) {
            return ArtifactType::EXECUTION;
        }

        if ($artifact instanceof SnapshotInterface) {
            return ArtifactType::SNAPSHOT;
        }

        if ($artifact instanceof VerifiedArtifactProjection) {
            return $artifact->artifactType();
        }

        if ($artifact instanceof RedactedArtifactInterface
            && array_key_exists($artifact->artifactType(), self::FIELD_ALLOWLIST)) {
            return $artifact->artifactType();
        }

        return ArtifactType::UNKNOWN;
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

            $projected = $kind === ArtifactType::SNAPSHOT && $key === ArtifactField::PAYLOAD
                ? $val : $this->projectValue($val, $depth + 1);

            $this->totalBytes += strlen($key);
            if ($kind !== ArtifactType::SNAPSHOT || $key !== ArtifactField::PAYLOAD) {
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

        $size = $this->computeSnapshotScalarSize($value);
        if ($size < 0) {
            return false;
        }

        $bytes += $size;
        return $bytes <= $this->limits->maxSnapshotPayloadBytes;
    }

    private function computeSnapshotScalarSize(mixed $value): int
    {
        return match (true) {
            is_string($value) => strlen($value),
            is_int($value), is_float($value) && is_finite($value) => 8,
            is_bool($value) => 1,
            $value === null => 0,
            default => -1,
        };
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
