<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\OpenTelemetry;

use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;
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
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * Builds realistic RunIt artifacts for OpenTelemetry bridge tests without going
 * through the full runtime, so individual mappers can be exercised in isolation
 * with deterministic identifiers and timestamps.
 */
final class RunItArtifactFactory
{
    public static function execution(
        string $id,
        string $name,
        string $kind,
        string $status,
        ?string $parentId = null,
    ): ExecutionInterface {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString($name),
            ExecutionKind::fromString($kind),
            $parentId !== null ? ExecutionId::fromString($parentId) : null,
        );

        $execution = Execution::start(ExecutionId::fromString($id), $definition, Instant::of(1_000));

        return match ($status) {
            'succeeded' => $execution->complete(ExecutionSuccess::create(), Instant::of(2_000)),
            'failed' => $execution->complete(
                ExecutionFailure::of(
                    ExecutionStatus::Failed,
                    ThrowableDescription::create('RuntimeException', 'execution failed', 0, null, null),
                ),
                Instant::of(2_000),
            ),
            default => $execution,
        };
    }

    public static function activity(
        string $id,
        string $executionId,
        string $name,
        string $kind,
        string $status,
        ?string $parentActivityId = null,
    ): ActivityInterface {
        $definition = ActivityDefinition::create(
            ActivityName::fromString($name),
            ActivityKind::fromString($kind),
            $parentActivityId !== null ? ActivityId::fromString($parentActivityId) : null,
        );

        $activity = Activity::start(
            ActivityId::fromString($id),
            ExecutionId::fromString($executionId),
            $definition,
            Instant::of(1_100),
        );

        return match ($status) {
            'succeeded' => $activity->complete(ActivityStatus::Succeeded, null, Instant::of(1_200)),
            'failed' => $activity->complete(
                ActivityStatus::Failed,
                ThrowableDescription::create('RuntimeException', 'activity failed', 0, null, null),
                Instant::of(1_200),
            ),
            'cancelled' => $activity->complete(ActivityStatus::Cancelled, null, Instant::of(1_200)),
            'timed_out' => $activity->complete(ActivityStatus::TimedOut, null, Instant::of(1_200)),
            default => $activity,
        };
    }

    /**
     * @param array<string, array<int|string, int|float|string|bool|null>|bool|float|int|string|null> $attributes
     */
    public static function counter(
        string $executionId,
        string $name,
        int $value,
        array $attributes = [],
    ): MeasurementInterface {
        return Measurement::counter(
            MeasurementName::fromString($name),
            ExecutionId::fromString($executionId),
            $value,
            null,
            Instant::of(1_500),
            null,
            AttributeCollection::fromArray($attributes),
            MeasurementUnit::Count,
        );
    }

    /**
     * @param array<string, array<int|string, int|float|string|bool|null>|bool|float|int|string|null> $attributes
     */
    public static function observation(
        string $id,
        string $executionId,
        string $severity,
        string $message,
        ?string $activityId = null,
        array $attributes = [],
    ): ObservationInterface {
        $activityIdValue = $activityId !== null ? ActivityId::fromString($activityId) : null;
        $attributesCollection = AttributeCollection::fromArray($attributes);

        return match ($severity) {
            'debug' => Observation::debug(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'info' => Observation::info(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'notice' => Observation::notice(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'warning' => Observation::warning(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'error' => Observation::error(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'critical' => Observation::critical(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'alert' => Observation::alert(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            'emergency' => Observation::emergency(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
            default => Observation::debug(ObservationId::fromString($id), ExecutionId::fromString($executionId), $message, $activityIdValue, null, $attributesCollection, null, Instant::of(1_800)),
        };
    }

    /**
     * @param mixed $data
     */
    public static function occurrence(
        string $id,
        string $executionId,
        string $type,
        $data = ['outcome' => 'ok'],
        ?string $activityId = null,
    ): OccurrenceInterface {
        return Occurrence::create(
            OccurrenceId::fromString($id),
            ExecutionId::fromString($executionId),
            OccurrenceType::fromString($type),
            'it-pack/test',
            Instant::of(1_600),
            $data,
            $activityId !== null ? ActivityId::fromString($activityId) : null,
        );
    }

    /**
     * @param mixed $payload
     */
    public static function snapshot(
        string $id,
        string $executionId,
        $payload,
        ?string $activityId = null,
        string $type = 'execution.snapshot',
    ): SnapshotInterface {
        return Snapshot::create(
            SnapshotId::fromString($id),
            ExecutionId::fromString($executionId),
            SnapshotType::fromString($type),
            1,
            $payload,
            new DeterministicHashCalculator(),
            $activityId !== null ? ActivityId::fromString($activityId) : null,
            null,
            SnapshotClassification::Checkpoint,
            Instant::of(1_700),
        );
    }

    public static function contextWithSecretAndPublic(): ExecutionContextInterface
    {
        $context = ExecutionContext::empty();
        $context = $context->with(ContextEntry::public(ContextKey::fromString('tenant.id'), 'acme'));
        $context = $context->with(ContextEntry::secret(ContextKey::fromString('secret.token'), 's3cr3t'));
        $context = $context->with(ContextEntry::public(ContextKey::fromString('correlation.id'), 'corr-123'));

        return $context;
    }
}
