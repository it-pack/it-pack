<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Factory\ExecutionFactory;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\Builder\RunItBuilderInterface;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use ItPack\RunIt\SDK\RunItInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Tests\Support\Fixture\ThrowingSnapshotStore;
use Throwable;

#[CoversClass(RunItBuilder::class)]
#[CoversClass(MissingBuilderDependencyException::class)]
final class RunItBuilderTest extends TestCase
{
    private function builder(): RunItBuilderInterface
    {
        return RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ;
    }

    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('builder.spec'),
            ExecutionKind::fromString('operation')
        );
    }

    public function testCreateReturnsBuilderInterface(): void
    {
        self::assertInstanceOf(RunItBuilderInterface::class, RunItBuilder::create());
    }

    public function testBuilderIsImmutable(): void
    {
        $base = RunItBuilder::create();
        $configured = $base->withPublisher(new InMemoryArtifactPublisher());

        self::assertNotSame($base, $configured);
    }

    public function testBuildReturnsInterfaceBackedRuntime(): void
    {
        $runtime = $this->builder()->build();

        self::assertInstanceOf(RunItInterface::class, $runtime);
    }

    public function testBuildValidatesRequiredPublisher(): void
    {
        $this->expectException(MissingBuilderDependencyException::class);

        RunItBuilder::create()
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    public function testBuildValidatesRequiredClock(): void
    {
        $this->expectException(MissingBuilderDependencyException::class);

        RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    public function testBuildValidatesRequiredSequenceAllocator(): void
    {
        $this->expectException(MissingBuilderDependencyException::class);

        RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    public function testBuildValidatesRequiredSerializer(): void
    {
        $this->expectException(MissingBuilderDependencyException::class);

        RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    public function testBuildValidatesRequiredIdentifierGenerator(): void
    {
        $this->expectException(MissingBuilderDependencyException::class);

        RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->build()
        ;
    }

    public function testBuiltRuntimeExecutesOperationAndReturnsResult(): void
    {
        $runtime = $this->builder()->build();

        $result = $runtime->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testDefaultTerminalSnapshotSinkOmitsPayloadByDefault(): void
    {
        $sink = new RecordingArtifactPublisher();
        $generator = new class () implements IdentifierGeneratorInterface {
            private int $sequence = 0;

            public function generate(): string
            {
                return ++$this->sequence === 1 ? 'caller-business-execution-id' : 'caller-id-' . $this->sequence;
            }
        };
        $runtime = RunItBuilder::create()
            ->withPublisher($sink)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator($generator)
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        $executions = array_values(array_filter(
            $sink->published(),
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ExecutionInterface,
        ));
        self::assertNotEmpty($executions);
        $execution = $executions[0]->normalize();

        $snapshots = array_values(array_filter(
            $sink->published(),
            static fn (ExecutionArtifactInterface $artifact): bool =>
                $artifact instanceof TrustedArtifactProjectionInterface && $artifact->artifactType() === 'snapshot',
        ));
        self::assertCount(1, $snapshots);
        $snapshot = $snapshots[0]->normalize();
        self::assertSame($execution['id'], $snapshot['execution_id']);
        self::assertArrayNotHasKey(ArtifactField::PAYLOAD, $snapshot);
        self::assertArrayNotHasKey(ArtifactField::CONTENT_HASH, $snapshot);
    }

    public function testSamplingPolicyDropsArtifacts(): void
    {
        $spy = new RecordingArtifactPublisher();
        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSamplingPolicy(new NeverSamplePolicy())
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        self::assertSame([], $spy->published);
    }

    public function testRedactorWrapsPublishedArtifacts(): void
    {
        $spy = new RecordingArtifactPublisher();
        $redactor = new TaggingRedactor();
        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withRedactor($redactor)
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        self::assertNotEmpty($spy->published);
        self::assertGreaterThan(0, $redactor->calls);
        self::assertSame('runit.redacted', $spy->published[0]->normalize()['name']);
    }

    public function testNewSeamWithersReturnNewBuilderInstances(): void
    {
        $base = $this->builder();
        $hashCalculator = new DeterministicHashCalculator();
        $executionFactory = new ExecutionFactory();
        $coordinator = new ExecutionLifecycleCoordinator();
        $snapshotFactory = new ExecutionSnapshotFactory(
            new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
            new DeterministicHashCalculator(),
        );

        self::assertNotSame($base, $base->withHashCalculator($hashCalculator));
        self::assertNotSame($base, $base->withExecutionFactory($executionFactory));
        self::assertNotSame($base, $base->withLifecycleCoordinator($coordinator));
        self::assertNotSame($base, $base->withSnapshotFactory($snapshotFactory));
        self::assertNotSame($base, $base->withThrowableMapper(new DefaultThrowableMapper()));
        self::assertNotSame($base, $base->withNestedExecutionPolicy(new ChildExecutionPolicy()));
        self::assertNotSame($base, $base->withCompletionPolicy(new AutoFailOpenActivitiesPolicy()));
        self::assertNotSame($base, $base->withExecutionEngine(new SynchronousExecutionEngine()));
    }

    public function testWithExecutionEngineInstallsTheInjectedEngine(): void
    {
        $runtime = $this->builder()
            ->withExecutionEngine(new class () implements ExecutionEngineInterface {
                public function run(ExecutionScopeInterface $scope, callable $operation): mixed
                {
                    return 'engine-installed';
                }

                public function capability(): EngineCapabilityInterface
                {
                    return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: false);
                }
            })
            ->build()
        ;

        self::assertSame(
            'engine-installed',
            $runtime->run(
                $this->definition(),
                static fn (): string => 'ignored'
            )
        );
    }

    public function testWithNestedExecutionPolicyDrivesNestedRunSemantics(): void
    {
        $runtime = $this->builder()->withNestedExecutionPolicy(new ActivityOnlyPolicy())->build();

        $inner = null;

        /** @var string $outer */
        $outer = $runtime->run(
            $this->definition(),
            static function () use ($runtime, &$inner): string {
                /** @var string $resolved */
                $resolved = $runtime->run(
                    ExecutionDefinition::create(
                        ExecutionName::fromString('nested'),
                        ExecutionKind::fromString('step')
                    ),
                    static fn (): string => 'activity',
                );
                $inner = $resolved;

                return 'outer';
            },
        );

        self::assertSame('outer', $outer);
        self::assertSame('activity', $inner);
    }

    public function testSecurityLimitsAndHandlerSeamsReturnNewBuilderInstances(): void
    {
        $base = $this->builder();

        self::assertNotSame($base, $base->withSecurityLimits(new SecurityLimits()));
        self::assertNotSame(
            $base,
            $base->withPublicationFailureHandler(static function (Throwable $failure): void {
            }),
        );
        self::assertNotSame(
            $base,
            $base->withSnapshotStoreFailureHandler(static function (Throwable $failure): void {
            }),
        );
        self::assertNotSame($base, $base->withPublisherStackFactory(new SpyPublisherStackFactory()));
    }

    public function testPublisherStackFactorySeamIsConsumedByBuild(): void
    {
        $factory = new SpyPublisherStackFactory();

        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withRedactor(new TaggingRedactor())
            ->withPublisherStackFactory($factory)
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        self::assertTrue(
            $factory->called,
            'build() must compose the publisher stack through the configured factory'
        );
        self::assertSame(3, $factory->argumentCount);
    }

    public function testHealthAwarePublisherStackFactoryReceivesReporter(): void
    {
        $factory = new SpyHealthAwarePublisherStackFactory();

        $this->builder()->withPublisherStackFactory($factory)->build();

        self::assertInstanceOf(InternalHealthReporterInterface::class, $factory->health);
    }

    public function testCustomPublisherStackDoesNotReceiveRawBusinessIdentity(): void
    {
        $seen = new InMemoryArtifactPublisher();
        $factory = new SpyPublisherStackFactory($seen);
        $generator = new class () implements IdentifierGeneratorInterface {
            private int $sequence = 0;

            public function generate(): string
            {
                return 'synthetic-secret-identity-' . ++$this->sequence;
            }
        };
        $runtime = $this->builder()
            ->withIdentifierGenerator($generator)
            ->withPublisherStackFactory($factory)
            ->build()
        ;

        $businessId = $runtime->run(
            $this->definition(),
            static fn (ExecutionScopeInterface $scope): string => $scope->execution()->id()->toString(),
        );

        self::assertTrue($factory->called);
        self::assertIsString($businessId);
        self::assertStringStartsWith('synthetic-secret-identity-', $businessId);
        $executions = array_values(array_filter(
            $seen->published(),
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ExecutionInterface,
        ));
        self::assertNotEmpty($executions);
        foreach ($executions as $execution) {
            self::assertInstanceOf(ExecutionInterface::class, $execution);
            self::assertNotSame($businessId, $execution->id()->toString());
            self::assertStringNotContainsString(
                'synthetic-secret-identity-',
                json_encode($execution->normalize(), JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testHealthAwareFactoryReportsThroughConfiguredCallback(): void
    {
        $factory = new SpyHealthAwarePublisherStackFactory();
        $failures = [];
        $this->builder()
            ->withPublisherStackFactory($factory)
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        self::assertNotNull($factory->health);
        $failure = new RuntimeException('stack health failure');
        $factory->health->report($failure, new RedactedTagArtifact(new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        }));
        self::assertSame([$failure], $failures);
    }

    public function testBoundExceededDropIsObservableThroughPublicationHandler(): void
    {
        $failures = [];

        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSecurityLimits(new SecurityLimits(maxArtifactsPerExecution: 1))
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        $result = $runtime->run(
            $this->definition(),
            static function (ExecutionScopeInterface $scope): int {
                $scope->occurrences()->record(
                    OccurrenceType::fromString('builder.test.v1'),
                    'builder',
                    ['n' => 1],
                );

                return 5;
            },
        );

        self::assertSame(5, $result, 'a bounded drop must not affect the business result');
        self::assertNotEmpty(
            $failures,
            'bound-exceeded drops must be observable through the publication handler'
        );

        foreach ($failures as $failure) {
            self::assertInstanceOf(BoundDropException::class, $failure);
            self::assertStringContainsString('artifact_count', $failure->getMessage());
        }

        // Startup execution + recorded occurrence + terminal publications all
        // share the one-artifact budget in this configuration.
        self::assertCount(3, $failures);
    }

    public function testRedactionIsDenyByDefault(): void
    {
        $spy = new RecordingArtifactPublisher();

        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;

        $thrown = new RuntimeException('secret failure detail');

        try {
            $runtime->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException) {
            // business exception surfaces unchanged; the recorded observation is
            // what we are inspecting here
        }

        $observations = array_filter(
            $spy->published,
            static fn (ExecutionArtifactInterface $a): bool => $a instanceof ObservationInterface
                || ($a instanceof RedactedArtifactInterface && $a->artifactType() === 'observation')
        );
        self::assertNotEmpty(
            $observations,
            'the failure must produce a published observation'
        );

        // Negative test: the published projection must exclude the secret.
        // ClassifiedArtifact proves classification ran, not that redaction ran.
        foreach ($spy->published as $artifact) {
            $normalized = $artifact->normalize();
            $encoded = json_encode($normalized);
            self::assertFalse($encoded === false);
            self::assertStringNotContainsString(
                'secret failure detail',
                $encoded,
                'secret throwable detail must not reach the published projection'
            );
        }
    }

    public function testDefaultObservationIsClassifiedBeforeSamplerAndSink(): void
    {
        $spy = new RecordingArtifactPublisher();
        $sampling = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;
                return true;
            }
        };
        $runtime = $this->builder()->withPublisher($spy)->withSamplingPolicy($sampling)->build();
        try {
            $runtime->run($this->definition(), static function (): never {
                throw new RuntimeException('synthetic-secret-failure');
            });
        } catch (RuntimeException) {
            // The business exception is expected.
        }

        foreach ([$sampling->seen, $spy->published] as $artifacts) {
            $observations = array_values(array_filter(
                $artifacts,
                static fn (ExecutionArtifactInterface $a): bool => $a instanceof ObservationInterface
            ));
            self::assertNotEmpty($observations);
            foreach ($observations as $observation) {
                self::assertInstanceOf(ObservationInterface::class, $observation);
                self::assertSame('[redacted]', $observation->message());
                self::assertSame([], $observation->attributes());
                self::assertStringNotContainsString(
                    'synthetic-secret',
                    json_encode($observation->normalize(), JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    public function testExplicitNullRedactorStillClassifiesObservations(): void
    {
        $spy = new RecordingArtifactPublisher();

        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withRedactor(redactor: null)
            ->build()
        ;

        try {
            $runtime->run($this->definition(), static function (): int {
                throw new RuntimeException('raw observation source');
            });
        } catch (Throwable) {
        }

        $observations = array_filter(
            $spy->published,
            static fn (ExecutionArtifactInterface $a): bool => $a instanceof ObservationInterface,
        );
        self::assertNotEmpty($observations);
        foreach ($observations as $observation) {
            self::assertInstanceOf(ObservationInterface::class, $observation);
            self::assertSame('[redacted]', $observation->message());
            self::assertStringNotContainsString(
                'raw observation source',
                json_encode($observation->normalize(), JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testSecurityLimitsPropagateToActivityDepthGate(): void
    {
        $runtime = $this->builder()
            ->withSecurityLimits(new SecurityLimits(maxActivityDepth: 1))
            ->build()
        ;

        try {
            $runtime->run($this->definition(), static function (ExecutionScopeInterface $scope): void {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('outer'),
                        ActivityKind::fromString('task')
                    ),
                    static function () use ($scope): void {
                        $scope->activities()->run(
                            ActivityDefinition::create(
                                ActivityName::fromString('inner'),
                                ActivityKind::fromString('task')
                            ),
                            static fn (): string => 'never reached',
                        );
                    },
                );
            });

            self::fail('configured maxActivityDepth must be enforced by the built runtime');
        } catch (ActivityDepthExceededException $activityDepthExceededException) {
            self::assertStringContainsString(
                '2',
                $activityDepthExceededException->getMessage(),
                'depth 2 must exceed the configured limit of 1'
            );
        }
    }

    public function testNullRedactorStillDropsUnclassifiedMeasurements(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $failures = [];
        $runtime = $this->builder()
            ->withPublisher($sink)
            ->withRedactor(null)
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->withSecurityLimits(new SecurityLimits(maxAttributeCount: 1))
            ->build()
        ;

        $runtime->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            $scope->measurements()->counter('limit.test', 1, attributes: AttributeCollection::fromArray([
                'first' => 1,
                'second' => 2,
            ]));
            return 1;
        });

        $published = $sink->published();
        $measurements = array_values(array_filter($published, static fn (ExecutionArtifactInterface $artifact): bool =>
            ($artifact->normalize()['name'] ?? null) === 'limit.test'));
        self::assertSame([], $measurements);
        self::assertNotEmpty($failures);
    }

    public function testCustomSnapshotFactoryPublishesTerminalSnapshot(): void
    {
        $sink = new RecordingArtifactPublisher();
        $failures = [];
        $runtime = $this->builder()
            ->withPublisher($sink)
            ->withSnapshotFactory(new ExecutionSnapshotFactory(
                new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
                new DeterministicHashCalculator(),
            ))
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        self::assertSame('result', $runtime->run($this->definition(), static fn (): string => 'result'));
        self::assertNotEmpty($sink->published);
        self::assertSame([], $failures);
        // Terminal snapshots are published as classified projections (payload omitted by default).
        $executions = array_values(array_filter(
            $sink->published,
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ExecutionInterface,
        ));
        self::assertNotEmpty($executions);
        $execution = $executions[0]->normalize();

        $snapshots = array_values(array_filter(
            $sink->published,
            static fn (ExecutionArtifactInterface $artifact): bool =>
                $artifact instanceof SnapshotInterface
                || ($artifact instanceof TrustedArtifactProjectionInterface
                    && $artifact->artifactType() === 'snapshot'),
        ));
        self::assertCount(1, $snapshots);
        $snapshot = $snapshots[0]->normalize();
        self::assertSame($execution['id'], $snapshot['execution_id']);
        self::assertArrayNotHasKey(ArtifactField::PAYLOAD, $snapshot);
        self::assertArrayNotHasKey(ArtifactField::CONTENT_HASH, $snapshot);
    }

    public function testCustomSnapshotFactoryCannotForgeExecutionOwnership(): void
    {
        $sink = new RecordingArtifactPublisher();
        $failures = [];
        $factory = $this->createMock(ExecutionSnapshotFactoryInterface::class);
        $factory->method('terminal')->willReturnCallback(function (
            ExecutionInterface $execution,
        ): ExecutionSnapshotInterface {
            $snapshot = $this->createMock(ExecutionSnapshotInterface::class);
            $real = new ExecutionSnapshotFactory(
                new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
                new DeterministicHashCalculator(),
            )->terminal($execution);
            $snapshot->method('id')->willReturn($real->id());
            $snapshot->method('executionId')->willReturn($execution->executionId());
            $normalized = $real->normalize();
            $normalized['execution_id'] = 'another-execution';
            $snapshot->method('normalize')->willReturn($normalized);

            return $snapshot;
        });
        $runtime = $this->builder()
            ->withPublisher($sink)
            ->withSnapshotFactory($factory)
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        self::assertSame('result', $runtime->run($this->definition(), static fn (): string => 'result'));
        self::assertCount(1, $failures);
        self::assertSame('provenance verification or issuance failed.', $failures[0]->getMessage());
        foreach ($sink->published as $artifact) {
            self::assertNotInstanceOf(SnapshotInterface::class, $artifact);
            self::assertFalse(
                $artifact instanceof TrustedArtifactProjectionInterface && $artifact->artifactType() === 'snapshot',
            );
            self::assertFalse(
                $artifact instanceof RedactedArtifactInterface && $artifact->artifactType() === 'snapshot',
            );
        }
    }

    public function testRecorderNormalizationFailuresPreserveBusinessResult(): void
    {
        $failures = [];
        $attributes = $this->createMock(AttributeCollectionInterface::class);
        $attributes->method('toArray')->willThrowException(new RuntimeException('sensitive normalization error'));
        $runtime = $this->builder()->withPublicationFailureHandler(
            static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
        )->build();

        $result = $runtime->run($this->definition(), static function (
            ExecutionScopeInterface $scope,
        ) use ($attributes): string {
            $scope->observations()->record('test', null, $attributes);
            $scope->measurements()->counter('test', 1, attributes: $attributes);
            $scope->occurrences()->record(
                OccurrenceType::fromString('test.event.v1'),
                'test',
                extensionAttributes: $attributes,
            );
            $scope->snapshots()->record(SnapshotType::fromString('test.snapshot'), [], attributes: $attributes);
            return 'result';
        });

        self::assertSame('result', $result);
        self::assertCount(4, $failures);
        foreach ($failures as $failure) {
            self::assertStringNotContainsString('sensitive normalization error', $failure->getMessage());
        }

        $businessFailure = new RuntimeException('business failure');
        try {
            $runtime->run($this->definition(), static function (
                ExecutionScopeInterface $scope,
            ) use ($attributes, $businessFailure): never {
                $scope->observations()->record('test', null, $attributes);
                throw $businessFailure;
            });
            self::fail('The original business exception must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($businessFailure, $runtimeException);
        }
    }

    public function testPublicationFailureHandlerReceivesAdapterFailure(): void
    {
        $failures = [];
        $runtime = RunItBuilder::create()
            ->withPublisher(new ThrowingArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        $result = $runtime->run($this->definition(), static fn (): int => 42);

        self::assertSame(42, $result, 'publication failure must not replace the business result');
        self::assertNotEmpty($failures, 'publication failure handler must receive the adapter fault');
    }

    public function testSnapshotStoreFailureHandlerReceivesStoreFailure(): void
    {
        $storeFailures = [];
        $runtime = $this->builder()
            ->withSnapshotStore(new ThrowingSnapshotStore())
            ->withSnapshotStoreFailureHandler(static function (Throwable $failure) use (&$storeFailures): void {
                $storeFailures[] = $failure;
            })
            ->build()
        ;

        $result = $runtime->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            $scope->snapshots()->record(SnapshotType::fromString('test.snapshot'), ['state' => 'captured']);

            return 99;
        });

        self::assertSame(99, $result);
        self::assertNotEmpty($storeFailures, 'snapshot-store failure handler must receive the store fault');
    }

    public function testBuildWiresConfiguredHashCalculatorIntoSnapshotFactory(): void
    {
        $spy = new SpyHashCalculator();

        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withHashCalculator($spy)
            ->build()
        ;

        $result = $runtime->run($this->definition(), static fn (): string => 'seams');

        self::assertSame('seams', $result);
        self::assertTrue(
            $spy->called,
            'configured hash calculator must be wired into the default snapshot factory'
        );
    }

    public function testBuildAcceptsAllCustomSeams(): void
    {
        $spy = new SpyHashCalculator();

        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withHashCalculator($spy)
            ->withExecutionFactory(new ExecutionFactory())
            ->withLifecycleCoordinator(new ExecutionLifecycleCoordinator())
            ->withSnapshotFactory(new ExecutionSnapshotFactory(
                new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
                $spy,
            ))
            ->build()
        ;

        $result = $runtime->run($this->definition(), static fn (): string => 'all-seams');

        self::assertSame('all-seams', $result);
        self::assertTrue(
            $spy->called,
            'configured hash calculator must be invoked through the custom snapshot factory'
        );
    }
}

final class SpyHashCalculator implements HashCalculatorInterface
{
    public bool $called = false;

    public function calculate(string $content): string
    {
        $this->called = true;

        return hash('sha256', $content);
    }
}

final class SpyPublisherStackFactory implements PublisherStackFactoryInterface
{
    public bool $called = false;

    public int $argumentCount = 0;

    public function __construct(private readonly ?ArtifactPublisherInterface $override = null)
    {
    }

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        $this->called = true;
        $this->argumentCount = func_num_args();

        return $this->override ?? $base;
    }
}

final class SpyHealthAwarePublisherStackFactory implements HealthAwarePublisherStackFactoryInterface
{
    public ?InternalHealthReporterInterface $health = null;

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        return $base;
    }

    public function composeWithHealth(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
        InternalHealthReporterInterface $health,
    ): ArtifactPublisherInterface {
        $this->health = $health;

        return $base;
    }
}

final class TaggingRedactor implements RedactorInterface
{
    public int $calls = 0;

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        ++$this->calls;
        return new RedactedTagArtifact($artifact);
    }
}

final readonly class RedactedTagArtifact implements ExecutionArtifactInterface
{
    public function __construct(private ExecutionArtifactInterface $inner)
    {
    }

    public function normalize(): array
    {
        $normalized = $this->inner->normalize();
        if (isset($normalized['name'])) {
            $normalized['name'] = 'redacted-name';
        }

        return $normalized;
    }
}
