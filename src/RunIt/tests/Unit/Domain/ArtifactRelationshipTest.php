<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Closure;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

/**
 * @phpstan-type Artifact Execution|Activity|Measurement|Observation|Occurrence|Snapshot
 */
#[CoversClass(Execution::class)]
#[CoversClass(Activity::class)]
#[CoversClass(Measurement::class)]
#[CoversClass(Observation::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Snapshot::class)]
final class ArtifactRelationshipTest extends Unit
{
    /**
     * @return array<string, array{Closure(): Artifact}>
     */
    public static function artifacts(): array
    {
        return [
            'execution' => [static fn (): Execution => Execution::start(
                ExecutionId::fromString('exec-1'),
                ExecutionDefinition::create(
                    ExecutionName::fromString('import'),
                    ExecutionKind::fromString('batch'),
                ),
                Instant::of(1700000000),
            )],
            'activity' => [static fn (): Activity => Activity::start(
                ActivityId::fromString('act-1'),
                ExecutionId::fromString('exec-1'),
                ActivityDefinition::create(
                    ActivityName::fromString('http.call'),
                    ActivityKind::fromString('client'),
                ),
                Instant::of(1700000000),
            )],
            'measurement' => [static fn (): Measurement => Measurement::counter(
                MeasurementName::fromString('files.loaded'),
                ExecutionId::fromString('exec-1'),
                42,
                instant: Instant::of(1700000000),
            )],
            'observation' => [static fn (): Observation => Observation::info(
                ObservationId::fromString('obs-1'),
                ExecutionId::fromString('exec-1'),
                'started',
                instant: Instant::of(1700000000),
            )],
            'occurrence' => [static fn (): Occurrence => Occurrence::create(
                OccurrenceId::fromString('occ-1'),
                ExecutionId::fromString('exec-1'),
                OccurrenceType::fromString('itpack.system.event.v1'),
                'svc',
                Instant::of(1700000000),
            )],
            'snapshot' => [static fn (): Snapshot => Snapshot::create(
                SnapshotId::fromString('snap-1'),
                ExecutionId::fromString('exec-1'),
                SnapshotType::fromString('execution.snapshot'),
                1,
                ['status' => 'running'],
                new DeterministicHashCalculator(),
                createdAt: Instant::of(1700000000),
            )],
        ];
    }

    /** @param Closure(): Artifact $create */
    #[DataProvider('artifacts')]
    public function testEveryArtifactIsAnExecutionArtifact(Closure $create): void
    {
        self::assertInstanceOf(ExecutionArtifactInterface::class, $create());
    }

    /** @param Closure(): Artifact $create */
    #[DataProvider('artifacts')]
    public function testEveryArtifactBelongsToExactlyOneExecution(Closure $create): void
    {
        self::assertSame('exec-1', $create()->executionId()->toString());
    }

    /** @param Closure(): Artifact $create */
    #[DataProvider('artifacts')]
    public function testEveryArtifactHasRequiredSequenceAndTimestamp(Closure $create): void
    {
        $artifact = $create();
        self::assertSame(0, $artifact->sequence());
        self::assertSame(1700000000, $artifact->timestamp());
    }

    /** @param Closure(): Artifact $create */
    #[DataProvider('artifacts')]
    public function testNormalizationIsDeterministicAndReferencesExecution(Closure $create): void
    {
        $artifact = $create();
        $first = $artifact->normalize();

        self::assertSame($first, $artifact->normalize());
        // The execution record identifies itself with `id`; other artifacts
        // reference it with `execution_id`. Both must be present and equal.
        $executionRef = $first['execution_id'] ?? $first['id'] ?? null;
        self::assertSame('exec-1', $executionRef);
    }

    /** @param Closure(): Artifact $create */
    #[DataProvider('artifacts')]
    public function testArtifactsDoNotRetainMutableSessions(Closure $create): void
    {
        self::assertTrue(new ReflectionClass($create())->isReadOnly());
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
