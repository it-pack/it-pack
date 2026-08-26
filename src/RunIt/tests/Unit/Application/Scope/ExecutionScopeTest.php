<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scope;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

final class ExecutionScopeTest extends Unit
{
    public function testMeasurementsAreBoundToExecutionAndPublished(): void
    {
        $kit = new RunItTestKit();
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static function ($scope) {
                $scope->measurements()->counter('files', 3);

                return null;
            },
        );

        $measurements = $this->ofType($kit, MeasurementInterface::class);
        self::assertCount(1, $measurements);
        self::assertSame('files', $measurements[0]->name()->toString());
        self::assertSame(3, $measurements[0]->value());
    }

    public function testObservationsCarryMappedThrowableAndSeverity(): void
    {
        $kit = new RunItTestKit();
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static function ($scope) {
                $scope->observations()->record(
                    'cache miss',
                    ObservationSeverity::Warning,
                    null,
                    new RuntimeException('no entry')
                );

                return null;
            },
        );

        $observations = $this->ofType($kit, ObservationInterface::class);
        self::assertCount(1, $observations);
        self::assertSame('cache miss', $observations[0]->message());
        self::assertSame(ObservationSeverity::Warning, $observations[0]->severity());
        self::assertNotNull($observations[0]->throwableDescription());
        self::assertSame('no entry', $observations[0]->throwableDescription()->message());
    }

    public function testOccurrencesAndSnapshotsArePublished(): void
    {
        $kit = new RunItTestKit();
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static function ($scope) {
                $scope->occurrences()->record(OccurrenceType::fromString('itpack.thing.done.v1'), 'app');
                $scope->snapshots()->record(
                    SnapshotType::fromString('itpack.state.v1'),
                    ['ok' => true]
                );

                return null;
            },
        );

        $occurrences = $this->ofType($kit, OccurrenceInterface::class);
        self::assertCount(1, $occurrences);
        self::assertSame('itpack.thing.done.v1', $occurrences[0]->type()->toString());

        // The runner also publishes a terminal execution snapshot; the recorder-published
        // snapshot is the one carrying the consumer's recorded type.
        $snapshots = array_values(array_filter(
            $this->ofType($kit, SnapshotInterface::class),
            static fn (SnapshotInterface $snapshot): bool => $snapshot->type()->toString() !== 'execution.terminal',
        ));
        self::assertCount(1, $snapshots);
        self::assertSame('itpack.state.v1', $snapshots[0]->type()->toString());
    }

    public function testNestedActivityBindsArtifactsAndRestoresContext(): void
    {
        $kit = new RunItTestKit();
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
                $scope->measurements()->counter('before-activity', 1);

                $scope->activities()->run(
                    ActivityDefinition::create(ActivityName::fromString('load'), ActivityKind::fromString('io')),
                    function ($activityScope) {
                        $activityScope->measurements()->counter('inside-activity', 7);

                        return 'inner';
                    },
                );

                $scope->measurements()->counter('after-activity', 2);

                return null;
            },
        );

        $measurements = $this->ofType($kit, MeasurementInterface::class);
        self::assertCount(3, $measurements);

        $inner = null;
        $outer = [];
        foreach ($measurements as $measurement) {
            if ($measurement->name()->toString() === 'inside-activity') {
                $inner = $measurement;

                continue;
            }

            $outer[] = $measurement;
        }

        self::assertNotNull($inner);
        self::assertNotNull($inner->activityId(), 'Measurements inside an activity must be bound to the activity.');
        foreach ($outer as $measurement) {
            self::assertNull($measurement->activityId(), 'Measurements outside an activity must not be bound.');
        }

        // The activity runner publishes both the started and the completed activity; only
        // the terminal record carries the outcome and activity bindings.
        $activities = array_values(array_filter(
            $this->ofType($kit, ActivityInterface::class),
            static fn (ActivityInterface $activity): bool => $activity->status()->isTerminal(),
        ));
        self::assertCount(1, $activities);
        self::assertTrue($activities[0]->status()->isTerminal());
    }

    public function testActivityFailureRethrowsOriginalThrowable(): void
    {
        $kit = new RunItTestKit();
        $thrown = new RuntimeException('activity broke');

        $caught = null;
        try {
            $kit->runner->run(
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                function ($scope) use ($thrown) {
                    $scope->activities()->run(
                        ActivityDefinition::create(ActivityName::fromString('load'), ActivityKind::fromString('io')),
                        static function () use ($thrown): void {
                            throw $thrown;
                        },
                    );

                },
            );
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertSame($thrown, $caught);

        $activities = array_values(array_filter(
            $this->ofType($kit, ActivityInterface::class),
            static fn (ActivityInterface $activity): bool => $activity->status()->isTerminal(),
        ));
        self::assertSame('failed', $activities[0]->status()->label());
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return list<T>
     */
    private function ofType(RunItTestKit $kit, string $type): array
    {
        $result = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof $type) {
                $result[] = $artifact;
            }
        }

        return $result;
    }
}
