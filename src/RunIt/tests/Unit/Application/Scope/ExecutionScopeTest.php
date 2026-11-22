<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scope;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

#[CoversClass(ExecutionScope::class)]
final class ExecutionScopeTest extends Unit
{
    public function testMeasurementsAreBoundToExecutionAndPublished(): void
    {
        $kit = new RunItTestKit();
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static function ($scope): null {
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
            static function ($scope): null {
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
            static function ($scope): null {
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
            function ($scope): null {
                $scope->measurements()->counter('before-activity', 1);

                $scope->activities()->run(
                    ActivityDefinition::create(ActivityName::fromString('load'), ActivityKind::fromString('io')),
                    function ($activityScope): string {
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
                function ($scope) use ($thrown): void {
                    $scope->activities()->run(
                        ActivityDefinition::create(ActivityName::fromString('load'), ActivityKind::fromString('io')),
                        static function () use ($thrown): never {
                            throw $thrown;
                        },
                    );

                },
            );
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
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

    public function testInvalidActivityContextFailurePolicyThrows(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid activity context failure policy.');
        new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            activityContextFailurePolicy: 'bogus',
        );
    }

    public function testSetCurrentActivityIdUpdatesTracking(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);

        $scope = $kit->buildScope($execution, $context);

        $activityId = ActivityId::fromString('act-1');
        $scope->setCurrentActivityId($activityId);

        self::assertSame($activityId, $scope->currentActivityId());

        $scope->setCurrentActivityId(null);
        self::assertNull($scope->currentActivityId());
    }

    public function testResolveActivityContextReturnsInvalidWhenActivityNotBound(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = $kit->buildScope($execution, $context);

        $activityId = ActivityId::fromString('act-1');
        $activity = $this->createMock(ActivityInterface::class);
        $activity->method('activityId')->willReturn($activityId);

        $scope->trackOpenActivity($activity);
        $scope->setCurrentActivityId($activityId);

        $ref = new ReflectionMethod(ExecutionScope::class, 'resolveActivityContext');
        $result = $ref->invoke($scope);

        self::assertSame([false, null, null], $result);
    }

    public function testReportActivityContextFailureReEntryGuardReturnsEarly(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            onActivityContextFailure: static function (string $reason): never {
                throw new RuntimeException('callback must not be invoked during guard');
            },
        );

        $reportingRef = new ReflectionProperty(ExecutionScope::class, 'activityContextFailureReporting');
        $reportingRef->setValue($scope, 1);

        $reportRef = new ReflectionMethod(ExecutionScope::class, 'reportActivityContextFailure');
        $reportRef->invoke($scope, 'test-reason');

        self::assertSame(1, $reportingRef->getValue($scope));
        $reportingRef->setValue($scope, 0);
    }

    public function testShouldDropThrowsOnActivityContextChangedWithThrowPolicy(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            activityContextFailurePolicy: 'throw',
        );

        $activityId = ActivityId::fromString('act-1');
        $scope->setCurrentActivityId($activityId);

        $shouldDropRef = new ReflectionMethod(ExecutionScope::class, 'shouldDrop');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Activity context changed.');
        $shouldDropRef->invoke($scope, 'different-activity-id');
    }

    public function testShouldDropReturnsTrueWhenActivityContextChangedWithDropPolicy(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            activityContextFailurePolicy: 'drop',
        );

        $activityId = ActivityId::fromString('act-1');
        $scope->setCurrentActivityId($activityId);

        $shouldDropRef = new ReflectionMethod(ExecutionScope::class, 'shouldDrop');

        self::assertTrue($shouldDropRef->invoke($scope, 'different-activity-id'));
    }

    public function testReportActivityContextFailureCallbackThrowsFallsBack(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            onActivityContextFailure: static function (string $reason): never {
                throw new RuntimeException('callback broken');
            },
        );

        $reportRef = new ReflectionMethod(ExecutionScope::class, 'reportActivityContextFailure');
        $reportRef->invoke($scope, 'test-reason');
    }

    public function testResolveActivityContextReturnsInvalidWhenExportIdNull(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $contextStorage = $this->createMock(ContextStorageInterface::class);

        $activityStorage = $this->createMock(ActivityContextStorageInterface::class);
        $activityScope = $this->createMock(ActivityScopeInterface::class);
        $activityStorage->method('current')->willReturn($activityScope);
        $activityScope->method('activity')->willReturn(
            $this->createMock(ActivityInterface::class)
        );
        $activityScope->method('parentScope')->willReturn(
            $this->createMock(ExecutionScopeInterface::class)
        );

        $authority = new ProvenanceAuthority();

        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            authority: $authority,
            contextStorage: $contextStorage,
            activityStorage: $activityStorage,
        );

        $contextStorage->method('current')->willReturn($scope);

        $ref = new ReflectionMethod(ExecutionScope::class, 'resolveActivityContext');
        $result = $ref->invoke($scope);

        self::assertSame([false, null, null], $result);
    }

    public function testResolveActivityContextCatchesThrowable(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $contextStorage = $this->createMock(ContextStorageInterface::class);
        $contextStorage->method('current')->willThrowException(new RuntimeException('storage broken'));

        $scope = new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new JsonSnapshotSerializer(),
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ObservationId::fromString(...),
                new SequenceIdentifierGenerator('obs')
            ),
            new StringIdentifierFactory(
                OccurrenceId::fromString(...),
                new SequenceIdentifierGenerator('occ')
            ),
            new StringIdentifierFactory(
                SnapshotId::fromString(...),
                new SequenceIdentifierGenerator('snap')
            ),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('act')
            ),
            new DeterministicHashCalculator(),
            contextStorage: $contextStorage,
        );

        $ref = new ReflectionMethod(ExecutionScope::class, 'resolveActivityContext');
        $result = $ref->invoke($scope);

        self::assertSame([false, null, null], $result);
    }
}
