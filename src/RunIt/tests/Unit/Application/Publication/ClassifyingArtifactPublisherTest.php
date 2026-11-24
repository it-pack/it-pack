<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedArtifact;
use ItPack\RunIt\Application\Publication\ClassifiedRedactedObservation;
use ItPack\RunIt\Application\Publication\ClassifiedRedactedOccurrence;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
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
use stdClass;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Throwable;

#[CoversClass(ClassifyingArtifactPublisher::class)]
final class ClassifyingArtifactPublisherTest extends Unit
{
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

        self::assertEmpty($inner->published());
    }

    public function testExecutionIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->execution('succeeded');

        $this->assertPublishedOnce($publisher, $artifact, $inner);
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
        $artifact = $this->execution('failed');

        $this->assertPublishedOnce($publisher, $artifact, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ExecutionInterface::class, $published);
        self::assertSame('failed', $published->status()->label());
        self::assertTrue($published->status()->isTerminal());
        self::assertSame('boom', $published->result()?->throwableDescription()?->message());
    }

    public function testFailedExecutionWithNullTraceIsClassified(): void
    {
        $inner = $this->innerPublisher();
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, ThrowableDescription::create(
                RuntimeException::class,
                'boom',
                1,
                ThrowableDescription::create(RuntimeException::class, 'cause', 2, null, null),
                null,
            )),
            Instant::of(2000),
        );

        $this->classifier($inner)->publish($execution);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(ExecutionInterface::class, $published);
        self::assertSame($execution->normalize(), $published->normalize());
    }

    public function testExecutionWithMissingThrowableTraceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $fields = $this->execution('failed')->normalize();

        if (is_array($fields['result'])
            && is_array($fields['result']['throwable'])
            && isset($fields['result']['throwable']['trace'])
        ) {
            unset($fields['result']['throwable']['trace']);
        }



        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn($fields);

        $this->classifier($inner)->publish($mock);

        self::assertCount(1, $inner->published());
        self::assertInstanceOf(ClassifiedArtifact::class, $inner->published()[0]);
    }

    public function testObservationIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->observation('obs-1');

        $this->assertPublishedOnce($publisher, $artifact, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ObservationInterface::class, $published);
        self::assertSame('test message', $published->message());
        self::assertSame(ObservationSeverity::Info, $published->severity());
    }

    public function testActivityIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->activity();

        $this->assertPublishedOnce($publisher, $artifact, $inner);
        self::assertSame('load', $artifact->definition()->name()->toString());
        self::assertSame('io', $artifact->definition()->kind()->toString());
        self::assertSame(ActivityStatus::Succeeded, $artifact->status());
    }

    public function testMeasurementIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->measurement();

        $this->assertPublishedOnce($publisher, $artifact, $inner);
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
        $artifact = $this->occurrence();

        $this->assertPublishedOnce($publisher, $artifact, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(OccurrenceInterface::class, $published);
        self::assertSame('itpack.custom.v1', $published->type()->toString());
        self::assertSame('source', $published->source());
    }

    public function testSnapshotIsClassifiedAndPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = $this->snapshot();

        $this->assertPublishedOnce($publisher, $artifact, $inner);
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

        self::assertEmpty($inner->published());
    }

    public function testCloseDelegatesToInnerWhenCloseable(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $publisher->close();

        self::assertEmpty($inner->published());
    }

    public function testMultipleArtifactsArePublishedInOrder(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $publisher->publish($this->observation('obs-1'));
        $publisher->publish($this->observation('obs-2'));
        $publisher->publish($this->observation('obs-3'));
        self::assertCount(3, $inner->published());

        [$first, $second, $third] = $inner->published();
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

        self::assertEmpty($inner->published());
    }

    public function testMissingRequiredFieldDropsArtifact(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements RedactedArtifactInterface {
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

        self::assertEmpty($inner->published());
    }

    public function testMissingRequiredFieldValidObservationIsPublished(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return [
                    'id' => 'obs-1',
                    'execution_id' => 'exec',
                    'severity' => 'info',
                    'level' => 2,
                    'message' => 'hi',
                    'sequence' => 0,
                    'timestamp' => 1000,
                    'attributes' => [],
                    'throwable' => null,
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
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

        self::assertEmpty($inner->published());
    }

    public function testFlushWhenInnerNotFlushableIsNoop(): void
    {
        $inner = new class () implements ArtifactPublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }
        };
        $publisher = new ClassifyingArtifactPublisher($inner);

        $publisher->flush();

    }

    public function testCloseWhenInnerNotCloseableIsNoop(): void
    {
        $inner = new class () implements ArtifactPublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }
        };
        $publisher = new ClassifyingArtifactPublisher($inner);

        $publisher->close();

    }

    public function testSnapshotWithChangedPayloadIsDropped(): void
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
                    'classification' => 'checkpoint',
                    'schema_version' => 1,
                    'created_at' => 1000,
                    'content_type' => 'application/json',
                    'content_hash' => 'abc',
                    'predecessor_id' => null,
                    'sequence' => 0,
                    'payload' => ['a' => 1],
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testMeasurementWithMissingFieldsIsDropped(): void
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
                return [];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testMeasurementWithInvalidKindFactoryIsDropped(): void
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

        self::assertEmpty($inner->published());
    }

    public function testMeasurementWithNonNumericValueIsDropped(): void
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
                    'kind' => 'counter',
                    'unit' => '1',
                    'value' => 'not-a-number',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'timestamp' => 1000,
                    'sequence' => 0,
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testMeasurementWithNonArrayAttributesIsDropped(): void
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
                    'kind' => 'counter',
                    'unit' => '1',
                    'value' => 5,
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'timestamp' => 1000,
                    'sequence' => 0,
                    'attributes' => 'not-array',
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testObservationWithInvalidSeverityIsDropped(): void
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

        self::assertEmpty($inner->published());
    }

    public function testOccurrenceWithMissingFieldsIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'occurrence';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testVerifiedArtifactProjectionUsesArtifactTypeMethod(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $artifact = new VerifiedArtifactProjection([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ], 'observation');

        $this->assertPublishedOnce($publisher, $artifact, $inner);
    }

    public function testRedactedArtifactWithUnknownTypeIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $redacted = new readonly class (['id' => 'x']) implements RedactedArtifactInterface {
            /** @param array<string, mixed> $data */
            public function __construct(private array $data)
            {
            }

            public function artifactType(): string
            {
                return 'unknown-type';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->data;
            }
        };

        $publisher->publish($redacted);

        self::assertEmpty($inner->published());
    }

    public function testDeeplyNestedStructureTriggersDepthLimit(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $nested = ['value' => 'leaf'];
        for ($i = 0; $i < 20; $i++) {
            $nested = ['child' => $nested];
        }

        $artifact = new readonly class ($nested) implements ExecutionArtifactInterface {
            /** @param array<string, mixed> $payload */
            public function __construct(private array $payload)
            {
            }

            public function artifactType(): string
            {
                return 'snapshot';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return [
                    'id' => 'snap-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'type' => 'state.v1',
                    'classification' => 'checkpoint',
                    'schema_version' => 1,
                    'created_at' => 1000,
                    'content_type' => 'application/json',
                    'content_hash' => 'abc',
                    'predecessor_id' => null,
                    'sequence' => 0,
                    'payload' => $this->payload,
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testLargeArrayTriggersNodeBudgetLimit(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        // Shallow, wide nested data to exceed MAX_NODES (1024) while staying
        // below MAX_DEPTH (16): 64 attributes, each a map of 64 scalar entries.
        $wide = [];
        for ($j = 0; $j < 64; $j++) {
            $wide["k_$j"] = 'v';
        }

        $attributes = [];
        for ($i = 0; $i < 64; $i++) {
            $attributes["attr_$i"] = $wide;
        }

        $artifact = new readonly class ($attributes) implements RedactedArtifactInterface {
            /** @param array<string, mixed> $attributes */
            public function __construct(private array $attributes)
            {
            }

            public function artifactType(): string
            {
                return 'measurement';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return [
                    'name' => 'req',
                    'kind' => 'counter',
                    'unit' => 'count',
                    'value' => 5,
                    'activity_id' => null,
                    'timestamp' => 1000,
                    'sequence' => 0,
                    'attributes' => $this->attributes,
                    'execution_id' => 'exec-1',
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
        // Node budget exhaustion truncates the attribute map: fewer than all 64
        // input attributes survive classification.
        $normalized = $published->normalize();
        self::assertLessThan(64, count((array)($normalized['attributes'] ?? [])));
    }

    public function testMeasurementMissingExecutionIdIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testMeasurementInvalidKindIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'bogus-kind',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
        self::assertSame('req', $published->normalize()['name']);
    }

    public function testMeasurementInvalidUnitIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'bogus-unit',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
        self::assertSame('req', $published->normalize()['name']);
    }

    public function testMeasurementCounterFloatValueIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5.5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testMeasurementInvalidAttributeValueIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => ['key' => new stdClass()],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testMeasurementNonStringNameIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 123,
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testObservationMissingIdIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testObservationInvalidAttributeValueIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => ['key' => new stdClass()],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testObservationNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testObservationInvalidSeverityIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'bogus',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testObservationInvalidThrowableIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => 'not-an-array',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionMissingContextKeyIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionInvalidFieldTypesAreNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
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
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionInvalidStatusIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'bogus-status',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionInvalidLinksAreNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [123],
            'sequence' => 0,
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
        self::assertSame('exec-1', $published->normalize()['id']);
    }

    public function testExecutionTerminalWithoutThrowableIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'failed',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => ['throwable' => null],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionSuccessfulWithThrowableIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'succeeded',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => [
                'throwable' => [
                    'class' => 'x',
                    'message' => 'y',
                    'code' => 1,
                    'previous' => null,
                    'trace' => '',
                ],
            ],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionFailedWithoutThrowableIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'failed',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testExecutionTerminalNullEndedAtIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'failed',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => [
                'throwable' => [
                    'class' => 'x',
                    'message' => 'y',
                    'code' => 1,
                    'previous' => null,
                    'trace' => '',
                ],
            ],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testActivityMissingRequiredKeyIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'succeeded',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            // missing throwable
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testActivityInvalidFieldTypesAreNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'succeeded',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testActivityInvalidStatusIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
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
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
        self::assertSame('act-1', $published->normalize()['id']);
    }

    public function testActivityInvalidThrowableIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'succeeded',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            'throwable' => 'not-an-array',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testActivityNonRunningNullEndedAtIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'failed',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testActivityInvalidDurationIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'failed',
            'started_at' => 1000,
            'ended_at' => 2000,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            'throwable' => null,
            'duration_ns' => 'not-an-int',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testSnapshotMissingRequiredKeyIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 1,
            'created_at' => 1000,
            'content_type' => 'application/json',
            'content_hash' => 'abc',
            'predecessor_id' => null,
            'sequence' => 0,
            // missing payload
            'attributes' => [],
        ]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testSnapshotInvalidClassificationIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'bogus',
            'schema_version' => 1,
            'created_at' => 1000,
            'content_type' => 'application/json',
            'content_hash' => 'abc',
            'predecessor_id' => null,
            'sequence' => 0,
            'payload' => ['key' => 'value'],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testOccurrenceMissingRequiredKeyIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'activity_id' => null,
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
            // missing subject
            'data_schema' => null,
            'version' => '1',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testOccurrenceInvalidFieldTypesAreNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'activity_id' => null,
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
            'subject' => null,
            'data_schema' => null,
            'version' => '1',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testOccurrenceInvalidExtensionsAreNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'activity_id' => null,
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => ['key' => new stdClass()],
            'sequence' => 0,
            'subject' => null,
            'data_schema' => null,
            'version' => '1',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testDeeplyNestedDataTriggersDepthLimit(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $nested = ['value' => 'leaf'];
        for ($i = 0; $i < 20; $i++) {
            $nested = ['level' => $nested];
        }

        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => $nested,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testHealthReporterThrowsOnMissingFieldIsContained(): void
    {
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->method('report')->willThrowException(new RuntimeException('health down'));

        $inner = $this->innerPublisher();
        $publisher = new ClassifyingArtifactPublisher($inner, $health);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    /**
     * @throws Throwable
     */
    public function assertPublishedOnce(
        ClassifyingArtifactPublisher $publisher,
        ExecutionArtifactInterface $mock,
        RecordingArtifactPublisher $inner
    ): void {
        $publisher->publish($mock);

        self::assertCount(1, $inner->published());
    }

    private function innerPublisher(): RecordingArtifactPublisher
    {
        return new RecordingArtifactPublisher();
    }

    private function classifier(RecordingArtifactPublisher $inner): ClassifyingArtifactPublisher
    {
        return new ClassifyingArtifactPublisher($inner);
    }

    private function execution(string $status): ExecutionInterface
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
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

    private function activity(): Activity
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io')
        );
        $activity = Activity::start(
            ActivityId::fromString('act-1'),
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

    private function measurement(): Measurement
    {
        return Measurement::counter(
            MeasurementName::fromString('requests'),
            ExecutionId::fromString('exec-1'),
            5
        );
    }

    private function occurrence(): Occurrence
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ-1'),
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

    private function snapshot(): Snapshot
    {
        return Snapshot::create(
            SnapshotId::fromString('snap-1'),
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

    public function testFlushDelegatesToInnerWhenInnerIsFlushable(): void
    {
        $inner = new class () implements ArtifactPublisherInterface, FlushablePublisherInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $published = [];

            public bool $flushed = false;

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                $this->published[] = $artifact;
            }

            public function flush(): void
            {
                $this->flushed = true;
            }
        };
        $publisher = new ClassifyingArtifactPublisher($inner);

        $publisher->flush();

        self::assertTrue($inner->flushed);
        self::assertEmpty($inner->published);
    }

    public function testCloseDelegatesToInnerWhenInnerIsCloseable(): void
    {
        $inner = new class () implements ArtifactPublisherInterface, CloseablePublisherInterface {
            public bool $closed = false;

            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };
        $publisher = new ClassifyingArtifactPublisher($inner);

        $publisher->close();

        self::assertTrue($inner->closed);
    }

    public function testSnapshotWithInvalidPayloadIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);

        $payload = ['value' => 'leaf'];
        for ($i = 0; $i < 20; $i++) {
            $payload = ['child' => $payload];
        }

        $artifact = new readonly class ($payload) implements ExecutionArtifactInterface {
            /** @param array<string, mixed> $payload */
            public function __construct(private array $payload)
            {
            }

            public function artifactType(): string
            {
                return 'snapshot';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return [
                    'id' => 'snap-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'type' => 'state.v1',
                    'classification' => 'checkpoint',
                    'schema_version' => 1,
                    'created_at' => 1000,
                    'content_type' => 'application/json',
                    'content_hash' => 'abc',
                    'predecessor_id' => null,
                    'sequence' => 0,
                    'payload' => $this->payload,
                    'attributes' => [],
                ];
            }
        };

        $publisher->publish($artifact);

        self::assertEmpty($inner->published());
    }

    public function testWrapRedactedClassifiedObservation(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'msg'
        );
        $redacted = new readonly class ($observation->normalize()) implements RedactedArtifactInterface {
            /** @param array<string, mixed> $data */
            public function __construct(private array $data)
            {
            }

            public function artifactType(): string
            {
                return 'observation';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->data;
            }
        };

        $publisher->publish($redacted);

        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedRedactedObservation::class, $published);
    }

    public function testWrapRedactedClassifiedOccurrence(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $redacted = new readonly class () implements RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'occurrence';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return [
                    'id' => 'occ-1',
                    'execution_id' => 'exec-1',
                    'activity_id' => null,
                    'type' => 'itpack.custom.v1',
                    'version' => '1',
                    'source' => 'source',
                    'subject' => null,
                    'time' => 1000,
                    'data' => [],
                    'data_schema' => null,
                    'content_type' => 'application/json',
                    'extension_attributes' => [],
                    'sequence' => 0,
                ];
            }
        };

        $publisher->publish($redacted);

        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedRedactedOccurrence::class, $published);
    }

    public function testMeasurementUnknownKindIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'unknown-kind',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementNonIntegerValueIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 'five',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationFactoryReturnsNullForUnknownSeverityIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'bogus',
            'level' => 0,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testObservationThrowableInvalidIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => ['class' => 'X', 'message' => 'm', 'code' => 'not-int', 'previous' => null, 'trace' => null],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
    }

    public function testStructuralFieldsNotIntactWithTypedObservationIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);

        $throwable = ['trace' => 'leaf'];
        for ($i = 0; $i < 20; $i++) {
            $throwable = ['child' => $throwable];
        }

        $mock->method('normalize')->willReturn([
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
            'throwable' => $throwable,
        ]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testRedactedTraceObservationIsDowngraded(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $redacted = new readonly class () implements RedactedArtifactInterface {
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
                    'severity' => 'trace',
                    'level' => 50,
                    'message' => 'trace msg',
                    'diagnostic_code' => null,
                    'sequence' => 0,
                    'timestamp' => 1000,
                    'attributes' => [],
                    'throwable' => null,
                ];
            }
        };

        $publisher->publish($redacted);

        self::assertCount(1, $inner->published());
    }

    public function testMissingRequiredFieldWithoutHealthReporterIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = new ClassifyingArtifactPublisher($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testMissingRequiredFieldWithFailingFallbackIsContained(): void
    {
        $inner = $this->innerPublisher();
        $publisher = new ClassifyingArtifactPublisher($inner);

        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([]);

        $publisher->publish($mock);

        self::assertEmpty($inner->published());
    }

    public function testMeasurementWithNonStringKindIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 123,
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithNonClosureFactoryIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'bogus-kind',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithInvalidUnitIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'invalid-unit',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithNonStringNameIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 123,
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithNonStringExecutionIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 123,
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithInvalidActivityIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => 123,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithNonIntTimestampIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 'not-int',
            'sequence' => 0,
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testMeasurementWithNonIntSequenceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 'not-int',
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithNullAttributesIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => null,
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithInvalidIdentifiersIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithNonStringSeverityIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 123,
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithNonIntTimestampIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 'not-int',
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testObservationWithNonIntSequenceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 'not-int',
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testThrowableNonArrayIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ObservationInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'info',
            'level' => 2,
            'message' => 'msg',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => 'not-array',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonStringStatusIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 123,
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
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
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonIntStartedAtIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 'not-int',
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonIntSequenceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 'not-int',
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonArrayContextIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => 'not-array',
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testExecutionWithNonArrayLinkedIdsIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ExecutionInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'exec-1',
            'name' => 'op',
            'kind' => 'task',
            'status' => 'created',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => 'not-array',
            'sequence' => 0,
            'context' => [],
            'result' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testActivityWithNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'running',
            'started_at' => 1000,
            'ended_at' => null,
            'sequence' => 0,
            'parent_activity_id' => null,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testActivityWithNonStringNameIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 123,
            'kind' => 'io',
            'status' => 'running',
            'started_at' => 1000,
            'ended_at' => null,
            'sequence' => 0,
            'parent_activity_id' => null,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testActivityWithNonIntStartedAtIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(ActivityInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'load',
            'kind' => 'io',
            'status' => 'running',
            'started_at' => 'not-int',
            'ended_at' => null,
            'sequence' => 0,
            'parent_activity_id' => null,
            'attributes' => [],
            'throwable' => null,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testSnapshotWithNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 1,
            'created_at' => 1000,
            'content_type' => 'application/json',
            'content_hash' => '',
            'predecessor_id' => null,
            'sequence' => 0,
            'payload' => [],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testSnapshotWithNonIntSchemaVersionIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 'not-int',
            'created_at' => 1000,
            'content_type' => 'application/json',
            'content_hash' => '',
            'predecessor_id' => null,
            'sequence' => 0,
            'payload' => [],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testSnapshotWithNonIntCreatedAtIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 1,
            'created_at' => 'not-int',
            'content_type' => 'application/json',
            'content_hash' => '',
            'predecessor_id' => null,
            'sequence' => 0,
            'payload' => [],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testSnapshotWithNonStringContentTypeIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 1,
            'created_at' => 1000,
            'content_type' => 123,
            'content_hash' => '',
            'predecessor_id' => null,
            'sequence' => 0,
            'payload' => [],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testSnapshotWithNonIntSequenceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(SnapshotInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'snap-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'state.v1',
            'classification' => 'checkpoint',
            'schema_version' => 1,
            'created_at' => 1000,
            'content_type' => 'application/json',
            'content_hash' => '',
            'predecessor_id' => null,
            'sequence' => 'not-int',
            'payload' => [],
            'attributes' => [],
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testOccurrenceWithNonStringIdIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 123,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'itpack.custom.v1',
            'version' => '1',
            'source' => 'source',
            'subject' => null,
            'time' => 1000,
            'data' => [],
            'data_schema' => null,
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testOccurrenceWithNonStringTypeIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 123,
            'version' => '1',
            'source' => 'source',
            'subject' => null,
            'time' => 1000,
            'data' => [],
            'data_schema' => null,
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testOccurrenceWithNonIntTimeIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'itpack.custom.v1',
            'version' => '1',
            'source' => 'source',
            'subject' => null,
            'time' => 'not-int',
            'data' => [],
            'data_schema' => null,
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testOccurrenceWithNonStringContentTypeIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'itpack.custom.v1',
            'version' => '1',
            'source' => 'source',
            'subject' => null,
            'time' => 1000,
            'data' => [],
            'data_schema' => null,
            'content_type' => 123,
            'extension_attributes' => [],
            'sequence' => 0,
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testOccurrenceWithNonIntSequenceIsNotTyped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(OccurrenceInterface::class);
        $mock->method('normalize')->willReturn([
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'itpack.custom.v1',
            'version' => '1',
            'source' => 'source',
            'subject' => null,
            'time' => 1000,
            'data' => [],
            'data_schema' => null,
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 'not-int',
        ]);

        $this->assertPublishedOnce($publisher, $mock, $inner);
        $published = $inner->published()[0];
        self::assertInstanceOf(ClassifiedArtifact::class, $published);
    }

    public function testArtifactKindWithVerifiedArtifactProjectionIsDroppedForUnknownType(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $projection = new VerifiedArtifactProjection(
            ['id' => 'x', 'execution_id' => 'exec'],
            'custom-type'
        );

        $publisher->publish($projection);

        self::assertEmpty($inner->published());
    }

    public function testArtifactKindWithUnknownRedactedTypeIsDropped(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $redacted = new readonly class () implements RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'unknown-kind';
            }

            public function normalize(): array
            {
                return ['id' => 'x'];
            }
        };

        $publisher->publish($redacted);

        self::assertEmpty($inner->published());
    }

    public function testProjectValueTruncatesLongString(): void
    {
        $inner = $this->innerPublisher();
        $publisher = $this->classifier($inner);
        $mock = $this->createMock(MeasurementInterface::class);
        $mock->method('normalize')->willReturn([
            'name' => 'req',
            'kind' => 'counter',
            'unit' => 'count',
            'value' => 5,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => ['long' => str_repeat('a', 5000)],
        ]);

        $publisher->publish($mock);

        self::assertCount(1, $inner->published());
    }
}
