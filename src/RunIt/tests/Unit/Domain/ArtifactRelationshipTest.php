<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\SequencedInterface;
use ItPack\RunIt\Contract\Domain\TimestampedInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ReflectionClass;

/**
 * @phpstan-type Artifact ExecutionArtifactInterface&ExecutionOwnedInterface&SequencedInterface&TimestampedInterface
 */
final class ArtifactRelationshipTest extends Unit
{
    /**
     * @return array<string, ExecutionArtifactInterface>
     */
    private function artifacts(): array
    {
        $instant = Instant::of(1700000000);
        $executionId = ExecutionId::fromString('exec-1');

        return [
            'execution' => Execution::start(
                $executionId,
                ExecutionDefinition::create(
                    ExecutionName::fromString('import'),
                    ExecutionKind::fromString('batch'),
                ),
                $instant,
            ),
            'activity' => Activity::start(
                ActivityId::fromString('act-1'),
                $executionId,
                ActivityDefinition::create(
                    ActivityName::fromString('http.call'),
                    ActivityKind::fromString('client'),
                ),
                $instant,
            ),
            'measurement' => Measurement::counter(
                MeasurementName::fromString('files.loaded'),
                $executionId,
                42,
                null,
                $instant,
            ),
            'observation' => Observation::info(
                ObservationId::fromString('obs-1'),
                $executionId,
                'started',
                null,
                null,
                null,
                null,
                $instant,
            ),
            'occurrence' => Occurrence::create(
                OccurrenceId::fromString('occ-1'),
                $executionId,
                OccurrenceType::fromString('itpack.system.event.v1'),
                'svc',
                $instant,
            ),
            'snapshot' => Snapshot::create(
                SnapshotId::fromString('snap-1'),
                $executionId,
                SnapshotType::fromString('execution.snapshot'),
                1,
                ['status' => 'running'],
                new DeterministicHashCalculator(),
                null,
                null,
                null,
                $instant,
            ),
        ];
    }

    public function testEveryArtifactIsAnExecutionArtifact(): void
    {
        foreach ($this->artifacts() as $artifact) {
            /** @var Artifact $artifact */
            self::assertInstanceOf(ExecutionArtifactInterface::class, $artifact);
        }
    }

    public function testEveryArtifactBelongsToExactlyOneExecution(): void
    {
        foreach ($this->artifacts() as $artifact) {
            /** @var Artifact $artifact */
            self::assertSame('exec-1', $artifact->executionId()->toString());
        }
    }

    public function testEveryArtifactHasRequiredSequenceAndTimestamp(): void
    {
        foreach ($this->artifacts() as $artifact) {
            /** @var Artifact $artifact */
            self::assertSame(0, $artifact->sequence());
            self::assertSame(1700000000, $artifact->timestamp());
        }
    }

    public function testNormalizationIsDeterministicAndReferencesExecution(): void
    {
        foreach ($this->artifacts() as $artifact) {
            /** @var Artifact $artifact */
            $first = $artifact->normalize();

            self::assertSame($first, $artifact->normalize());
            // The execution record identifies itself with `id`; other artifacts
            // reference it with `execution_id`. Both must be present and equal.
            $executionRef = $first['execution_id'] ?? $first['id'] ?? null;
            self::assertSame('exec-1', $executionRef);
        }
    }

    public function testArtifactsDoNotRetainMutableSessions(): void
    {
        foreach ($this->artifacts() as $artifact) {
            /** @var Artifact $artifact */
            self::assertTrue(new ReflectionClass($artifact)->isReadOnly());
        }
    }

    public function testActivityBoundArtifactsCarryActivityIdentity(): void
    {
        $activity = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            ActivityDefinition::create(
                ActivityName::fromString('http.call'),
                ActivityKind::fromString('client'),
            ),
            Instant::of(1),
        );

        $measurement = Measurement::gauge(
            MeasurementName::fromString('latency'),
            ExecutionId::fromString('exec-1'),
            1.5,
            ActivityId::fromString('act-1'),
            Instant::of(1),
        );

        self::assertSame('act-1', $activity->activityId()->toString());
        self::assertSame('act-1', $measurement->activityId()?->toString());
    }
}
