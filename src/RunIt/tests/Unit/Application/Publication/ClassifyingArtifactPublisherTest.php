<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
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
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\RecordingArtifactPublisher;

#[CoversClass(ClassifyingArtifactPublisher::class)]
final class ClassifyingArtifactPublisherTest extends Unit
{
    private function innerPublisher(): RecordingArtifactPublisher
    {
        return new RecordingArtifactPublisher();
    }

    private function classifier(RecordingArtifactPublisher $inner): ClassifyingArtifactPublisher
    {
        return new ClassifyingArtifactPublisher($inner);
    }

    private function execution(string $id, string $status): ExecutionInterface
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
        $execution = Execution::create(
            ExecutionId::fromString($id),
            $definition,
            Instant::of(1000)
        );

        $execution = $execution->beginRunning();
        if ($status === 'succeeded') {
            return $execution->complete(ExecutionSuccess::create(), Instant::of(2000));
        }

        $throwable = ThrowableDescription::create(
            RuntimeException::class,
            'boom',
            1,
            null,
            ''
        );
        $failure = ExecutionFailure::of(
            ExecutionStatus::Failed,
            $throwable
        );

        return $execution->complete($failure, Instant::of(2000));
    }

    private function observation(string $id): ObservationInterface
    {
        return Observation::info(
            ObservationId::fromString($id),
            ExecutionId::fromString('exec-1'),
            'test message'
        );
    }

    private function activity(string $id): Activity
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io')
        );
        $activity = Activity::start(
            ActivityId::fromString($id),
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1000)
        );

        return $activity->complete(
            ActivityStatus::Succeeded,
            null,
            Instant::of(2000)
        );
    }

    private function measurement(string $name): Measurement
    {
        return Measurement::counter(
            MeasurementName::fromString($name),
            ExecutionId::fromString('exec-1'),
            5
        );
    }

    private function occurrence(string $id): Occurrence
    {
        return Occurrence::create(
            OccurrenceId::fromString($id),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.custom.v1'),
            'source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial()
        );
    }

    private function snapshot(string $id): Snapshot
    {
        return Snapshot::create(
            SnapshotId::fromString($id),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('state.v1'),
            1,
            ['key' => 'value'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha256', $content);
                }
            },
            null,
            null,
            SnapshotClassification::Checkpoint,
            Instant::of(1000),
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial()
        );
    }

    public function testUnknownKindIsDroppedWithoutCallingInner(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'unknown-kind';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testExecutionIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->execution('exec-1', 'succeeded');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(ExecutionInterface::class, $published);
        self::assertSame('op', $published->definition()->name()->toString());
        self::assertSame('task', $published->definition()->kind()->toString());
        self::assertSame('succeeded', $published->status()->label());
        self::assertTrue($published->status()->isTerminal());
    }

    public function testFailedExecutionIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->execution('exec-1', 'failed');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(ExecutionInterface::class, $published);
        self::assertSame('failed', $published->status()->label());
        self::assertTrue($published->status()->isTerminal());
        self::assertSame('boom', $published->result()?->throwableDescription()?->message());
    }

    public function testObservationIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->observation('obs-1');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(ObservationInterface::class, $published);
        self::assertSame('test message', $published->message());
        self::assertSame(ObservationSeverity::Info, $published->severity());
    }

    public function testActivityIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->activity('act-1');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        self::assertSame('load', $artifact->definition()->name()->toString());
        self::assertSame('io', $artifact->definition()->kind()->toString());
        self::assertSame(ActivityStatus::Succeeded, $artifact->status());
    }

    public function testMeasurementIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->measurement('requests');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(MeasurementInterface::class, $published);
        self::assertSame('requests', $published->name()->toString());
        self::assertSame(MeasurementKind::Counter, $published->kind());
        self::assertSame(5, $published->value());
    }

    public function testOccurrenceIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->occurrence('occ-1');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(OccurrenceInterface::class, $published);
        self::assertSame('itpack.custom.v1', $published->type()->toString());
        self::assertSame('source', $published->source());
    }

    public function testSnapshotIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->snapshot('snap-1');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(SnapshotInterface::class, $published);
        self::assertSame('state.v1', $published->type()->toString());
        self::assertSame(SnapshotClassification::Checkpoint, $published->classification());
    }

    public function testFlushDelegatesToInnerWhenFlushable(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $publisher->flush();

        self::assertCount(0, $inner->published());
    }

    public function testCloseDelegatesToInnerWhenCloseable(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $publisher->close();

        self::assertCount(0, $inner->published());
    }

    public function testMultipleArtifactsArePublishedInOrder(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $publisher->publish($this->observation('obs-1'));
        $publisher->publish($this->observation('obs-2'));
        $publisher->publish($this->observation('obs-3'));

        self::assertCount(3, $inner->published());
        $first = $inner->published()[0];
        $second = $inner->published()[1];
        $third = $inner->published()[2];
        self::assertInstanceOf(ObservationInterface::class, $first);
        self::assertInstanceOf(ObservationInterface::class, $second);
        self::assertInstanceOf(ObservationInterface::class, $third);
        self::assertSame('obs-1', $first->id()->toString());
        self::assertSame('obs-2', $second->id()->toString());
        self::assertSame('obs-3', $third->id()->toString());
    }

    public function testUnknownArtifactKindIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'weird-kind';
            }

            public function normalize(): array
            {
                return ['id' => 'x', 'execution_id' => 'exec'];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testMissingRequiredFieldDropsArtifact(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['execution_id' => 'exec', 'message' => 'hi'];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testObservationWithUnknownSeverityIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'obs-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'severity' => 'bogus-severity',
                    'level' => 0,
                    'message' => 'msg',
                    'diagnostic_code' => null,
                    'sequence' => 0,
                    'timestamp' => 1000,
                    'attributes' => [],
                    'throwable' => null,
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testMeasurementWithInvalidKindIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'measurement';
            }

            public function normalize(): array
            {
                return [
                    'name' => 'req',
                    'kind' => 'bogus-kind',
                    'unit' => '1',
                    'value' => 5,
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'timestamp' => 1000,
                    'sequence' => 0,
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testActivityWithInvalidStatusIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'activity';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'act-1',
                    'execution_id' => 'exec-1',
                    'name' => 'load',
                    'kind' => 'io',
                    'status' => 'bogus-status',
                    'started_at' => 1000,
                    'ended_at' => 2000,
                    'parent_activity_id' => null,
                    'sequence' => 0,
                    'attributes' => [],
                    'throwable' => null,
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testSnapshotWithInvalidClassificationIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'snapshot';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'snap-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'type' => 'state.v1',
                    'classification' => 'bogus-class',
                    'schema_version' => 1,
                    'created_at' => 1000,
                    'content_type' => 'application/json',
                    'content_hash' => '',
                    'predecessor_id' => null,
                    'sequence' => 0,
                    'payload' => [],
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testExecutionWithMissingRequiredKeysIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'exec-1',
                    'name' => 'op',
                    'kind' => 'task',
                    'status' => 'created',
                    'started_at' => 1000,
                    'ended_at' => null,
                    'parent_execution_id' => null,
                    'linked_execution_ids' => [],
                    'sequence' => 0,
                    'context' => [],
                    'result' => null,
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testUnknownArtifactKindReturnsUnknownAndIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'weird-kind';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testSnapshotPayloadOmittedPathIsTaken(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $redacted = new readonly class (['id' => 'snap-1']) implements RedactedArtifactInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data)
            {
            }

            public function artifactType(): string
            {
                return 'snapshot';
            }

            /**
             * @return array<string, mixed>
             */
            public function normalize(): array
            {
                return $this->data;
            }
        };

        $publisher->publish($redacted);

        self::assertCount(0, $inner->published());
    }

    public function testStructuralFieldsNotIntactDropsArtifact(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'obs-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'severity' => 'warning',
                    'level' => 4,
                    'message' => 'msg',
                    'diagnostic_code' => null,
                    'sequence' => 0,
                    'timestamp' => 1000,
                    'attributes' => [],
                    'throwable' => ['class' => 'X', 'message' => 'm', 'code' => 1, 'previous' => null, 'trace' => null],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }

    public function testUnchangedStructureMismatchDropsArtifact(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'obs-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'severity' => 'warning',
                    'level' => 4,
                    'message' => 'msg',
                    'diagnostic_code' => null,
                    'sequence' => 0,
                    'timestamp' => 1000,
                    'attributes' => [],
                    'throwable' => [
                        'class' => 'X',
                        'message' => 'm',
                        'code' => 1,
                        'previous' => null,
                        'trace' => 'changed',
                    ],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(0, $inner->published());
    }
}
