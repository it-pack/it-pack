<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use Codeception\Attribute\DataProvider;
use InvalidArgumentException;
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
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
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
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
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
use ItPack\RunIt\SDK\DefaultRunIt;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\RedactedTagArtifact;
use Tests\Support\Fixture\RunItFactories;
use Tests\Support\Fixture\SpyHashCalculator;
use Tests\Support\Fixture\SpyHealthAwarePublisherStackFactory;
use Tests\Support\Fixture\SpyPublisherStackFactory;
use Tests\Support\Fixture\TaggingRedactor;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Tests\Support\Fixture\ThrowingSnapshotStore;
use Throwable;

#[CoversClass(RunItBuilder::class)]
#[CoversClass(MissingBuilderDependencyException::class)]
final class RunItBuilderTest extends TestCase
{
    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('builder.spec'),
            ExecutionKind::fromString('operation')
        );
    }

    private function snapshotFactory(?HashCalculatorInterface $hashCalculator = null): ExecutionSnapshotFactory
    {
        return new ExecutionSnapshotFactory(
            new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
            $hashCalculator ?? new DeterministicHashCalculator(),
        );
    }

    /**
     * @param list<ExecutionArtifactInterface> $published
     * @return list<ExecutionArtifactInterface>
     */
    private function executionsOf(array $published): array
    {
        return array_values(array_filter(
            $published,
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ExecutionInterface,
        ));
    }

    /**
     * @param list<ExecutionArtifactInterface> $published
     * @return list<ExecutionArtifactInterface>
     */
    private function snapshotsOf(array $published): array
    {
        return array_values(array_filter(
            $published,
            static fn (ExecutionArtifactInterface $artifact): bool =>
                $artifact instanceof SnapshotInterface
                || ($artifact instanceof TrustedArtifactProjectionInterface
                    && $artifact->artifactType() === 'snapshot'),
        ));
    }

    /**
     * @param array<string, mixed> $execution
     * @param array<string, mixed> $snapshot
     */
    private function assertSnapshotMatchesExecution(array $execution, array $snapshot): void
    {
        self::assertSame($execution['id'], $snapshot['execution_id']);
        self::assertArrayHasKey(ArtifactField::PAYLOAD, $snapshot);
        self::assertArrayHasKey(ArtifactField::CONTENT_HASH, $snapshot);
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
        $runtime = RunItFactories::configuredBuilder()->build();

        self::assertInstanceOf(DefaultRunIt::class, $runtime);
    }

    #[DataProvider('requiredDependencies')]
    public function testBuildValidatesRequiredDependency(string $dependency): void
    {
        $this->expectException(MissingBuilderDependencyException::class);
        $this->buildMissing($dependency);
    }

    public function testBuiltRuntimeExecutesOperationAndReturnsResult(): void
    {
        $runtime = RunItFactories::configuredBuilder()->build();

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
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($sink)
            ->withIdentifierGenerator($generator)
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        $executions = $this->executionsOf($sink->published());
        self::assertNotEmpty($executions);
        $execution = $executions[0]->normalize();

        $snapshots = $this->snapshotsOf($sink->published());
        self::assertCount(1, $snapshots);
        $snapshot = $snapshots[0]->normalize();
        $this->assertSnapshotMatchesExecution($execution, $snapshot);
    }

    public function testSamplingPolicyDropsArtifacts(): void
    {
        $spy = new RecordingArtifactPublisher();
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($spy)
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
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($spy)
            ->withRedactor($redactor)
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        self::assertNotEmpty($spy->published);
        self::assertGreaterThan(0, $redactor->calls);
        self::assertSame('runit.redacted', $spy->published[0]->normalize()['name']);
    }

    #[DataProvider('immutableSeams')]
    public function testSeamReturnsNewBuilderInstance(string $seam): void
    {
        $base = RunItFactories::configuredBuilder();
        $configured = match ($seam) {
            'hashCalculator' => $base->withHashCalculator(new DeterministicHashCalculator()),
            'executionFactory' => $base->withExecutionFactory(new ExecutionFactory()),
            'lifecycleCoordinator' => $base->withLifecycleCoordinator(new ExecutionLifecycleCoordinator()),
            'snapshotFactory' => $base->withSnapshotFactory(new ExecutionSnapshotFactory(
                new StringIdentifierFactory(SnapshotId::fromString(...), new RandomIdentifierGenerator()),
                new DeterministicHashCalculator(),
            )),
            'throwableMapper' => $base->withThrowableMapper(new DefaultThrowableMapper()),
            'nestedExecutionPolicy' => $base->withNestedExecutionPolicy(new ChildExecutionPolicy()),
            'completionPolicy' => $base->withCompletionPolicy(new AutoFailOpenActivitiesPolicy()),
            'executionEngine' => $base->withExecutionEngine(new SynchronousExecutionEngine()),
            'securityLimits' => $base->withSecurityLimits(new SecurityLimits()),
            'publicationFailureHandler' => $base->withPublicationFailureHandler(
                static function (Throwable $failure): void {
                }
            ),
            'snapshotStoreFailureHandler' => $base->withSnapshotStoreFailureHandler(
                static function (Throwable $failure): void {
                }
            ),
            'publisherStackFactory' => $base->withPublisherStackFactory(new SpyPublisherStackFactory()),
            'activityContextPolicy' => $base->withActivityContextPolicy('drop'),
            'activityContextAttachFailureFallback' => $base->withActivityContextAttachFailureFallback(true),
            default => throw new InvalidArgumentException("Unknown seam: $seam"),
        };
        self::assertNotSame($base, $configured);
    }

    /** @return iterable<string, array{0: string}> */
    public static function immutableSeams(): iterable
    {
        yield 'hashCalculator' => ['hashCalculator'];
        yield 'executionFactory' => ['executionFactory'];
        yield 'lifecycleCoordinator' => ['lifecycleCoordinator'];
        yield 'snapshotFactory' => ['snapshotFactory'];
        yield 'throwableMapper' => ['throwableMapper'];
        yield 'nestedExecutionPolicy' => ['nestedExecutionPolicy'];
        yield 'completionPolicy' => ['completionPolicy'];
        yield 'executionEngine' => ['executionEngine'];
        yield 'securityLimits' => ['securityLimits'];
        yield 'publicationFailureHandler' => ['publicationFailureHandler'];
        yield 'snapshotStoreFailureHandler' => ['snapshotStoreFailureHandler'];
        yield 'publisherStackFactory' => ['publisherStackFactory'];
        yield 'activityContextPolicy' => ['activityContextPolicy'];
        yield 'activityContextAttachFailureFallback' => ['activityContextAttachFailureFallback'];
    }

    public function testWithExecutionEngineInstallsTheInjectedEngine(): void
    {
        $runtime = RunItFactories::configuredBuilder()
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
        $runtime = RunItFactories::configuredBuilder()->withNestedExecutionPolicy(new ActivityOnlyPolicy())->build();

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

    public function testPublisherStackFactorySeamIsConsumedByBuild(): void
    {
        $factory = new SpyPublisherStackFactory();

        $runtime = RunItFactories::configuredBuilder()
            ->withPublisherStackFactory($factory)
            ->withRedactor(new TaggingRedactor())
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

        RunItFactories::configuredBuilder()->withPublisherStackFactory($factory)->build();

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
        $runtime = RunItFactories::configuredBuilder()
            ->withIdentifierGenerator($generator)
            ->withRedactor(null)
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
        foreach ($seen->published() as $artifact) {
            $normalized = $artifact->normalize();
            unset($normalized['id'], $normalized['payload']);
            self::assertStringNotContainsString(
                'synthetic-secret-identity-',
                json_encode($normalized, JSON_THROW_ON_ERROR),
            );
        }

        $executions = $this->executionsOf($seen->published());
        self::assertNotEmpty($executions);
        foreach ($executions as $execution) {
            self::assertInstanceOf(ExecutionInterface::class, $execution);
            self::assertNotSame($businessId, $execution->id()->toString());
            $executionNormalized = $execution->normalize();
            unset($executionNormalized['payload']);
            self::assertStringNotContainsString(
                'synthetic-secret-identity-',
                json_encode($executionNormalized, JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testHealthAwareFactoryReportsThroughConfiguredCallback(): void
    {
        $factory = new SpyHealthAwarePublisherStackFactory();
        $failures = [];
        RunItFactories::configuredBuilder()
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

        $runtime = RunItFactories::configuredBuilder()
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

        $runtime = RunItFactories::configuredBuilder()->withPublisher($spy)->build();

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
            self::assertNotFalse($encoded);
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
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($spy)
            ->withSamplingPolicy($sampling)
            ->build()
        ;
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

        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($spy)
            ->withRedactor(null)
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
            self::assertStringContainsString(
                'raw observation source',
                json_encode($observation->normalize(), JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testSecurityLimitsPropagateToActivityDepthGate(): void
    {
        $runtime = RunItFactories::configuredBuilder()
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
        $dropReports = [];
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($sink)
            ->withRedactor(null)
            ->withSecurityLimits(new SecurityLimits(maxAttributeCount: 1))
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$dropReports): void {
                $dropReports[] = $failure;
            })
            ->build()
        ;

        $runtime->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            $scope->measurements()->counter('limit.test', 1, attributes: AttributeCollection::fromArray([
                'first' => 1,
                'second' => 2,
            ]));
            return 1;
        });

        // SDK measurements are dropped before sampling and never reach the sink.
        $measurements = array_values(array_filter(
            $sink->published(),
            static fn (ExecutionArtifactInterface $artifact): bool =>
                ($artifact->normalize()['name'] ?? null) === 'limit.test',
        ));
        self::assertEmpty($measurements);
        self::assertNotEmpty($dropReports);
        self::assertStringContainsString(
            'Default measurement classification dropped an unclassified measurement.',
            $dropReports[0]->getMessage(),
        );
    }

    public function testCustomSnapshotFactoryPublishesTerminalSnapshot(): void
    {
        $sink = new RecordingArtifactPublisher();
        $failures = [];
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher($sink)
            ->withSnapshotFactory($this->snapshotFactory())
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

        self::assertSame('result', $runtime->run($this->definition(), static fn (): string => 'result'));
        self::assertNotEmpty($sink->published);
        self::assertSame([], $failures);
        // Terminal snapshots are published as classified projections (payload omitted by default).
        $executions = $this->executionsOf($sink->published);
        self::assertNotEmpty($executions);
        $execution = $executions[0]->normalize();

        $snapshots = $this->snapshotsOf($sink->published);
        self::assertCount(1, $snapshots);
        $snapshot = $snapshots[0]->normalize();
        $this->assertSnapshotMatchesExecution($execution, $snapshot);
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
        $runtime = RunItFactories::configuredBuilder()
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
        $runtime = RunItFactories::configuredBuilder()
            ->withPublicationFailureHandler(static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            })
            ->build()
        ;

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
        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher(new ThrowingArtifactPublisher())
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
        $runtime = RunItFactories::configuredBuilder()
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

        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher(new InMemoryArtifactPublisher())
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

    #[DataProvider('activityContextPolicies')]
    public function testWithActivityContextPolicyAcceptsValidValues(string $policy): void
    {
        $base = RunItFactories::configuredBuilder();
        $builder = $base->withActivityContextPolicy($policy);
        self::assertNotSame($base, $builder);
        $runner = $builder->build()->executionRunner();
        /** @var DefaultExecutionRunner $runner */
        self::assertSame($policy, $runner->activityContextPolicy());
    }

    /** @return iterable<string, array{string}> */
    public static function activityContextPolicies(): iterable
    {
        yield 'fallback' => ['fallback'];
        yield 'drop' => ['drop'];
        yield 'throw' => ['throw'];
    }

    public function testWithActivityContextPolicyRejectsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown activity context policy.');
        $base = RunItFactories::configuredBuilder();
        $base->withActivityContextPolicy('invalid-policy');
    }

    public function testWithActivityContextAttachFailureFallback(): void
    {
        $base = RunItFactories::configuredBuilder();
        $enabled = $base->withActivityContextAttachFailureFallback(true);
        self::assertNotSame($base, $enabled);

        $runtime = $enabled->build();
        self::assertInstanceOf(DefaultRunIt::class, $runtime);

        $disabled = $enabled->withActivityContextAttachFailureFallback(false);
        self::assertNotSame($enabled, $disabled);

        $runtime = $disabled->build();
        self::assertInstanceOf(DefaultRunIt::class, $runtime);
    }

    public function testBuildAcceptsAllCustomSeams(): void
    {
        $spy = new SpyHashCalculator();

        $runtime = RunItFactories::configuredBuilder()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withHashCalculator($spy)
            ->withExecutionFactory(new ExecutionFactory())
            ->withLifecycleCoordinator(new ExecutionLifecycleCoordinator())
            ->withSnapshotFactory($this->snapshotFactory($spy))
            ->build()
        ;

        $result = $runtime->run($this->definition(), static fn (): string => 'all-seams');

        self::assertSame('all-seams', $result);
        self::assertTrue(
            $spy->called,
            'configured hash calculator must be invoked through the custom snapshot factory'
        );
    }

    /** @return iterable<string, array{string}> */
    public static function requiredDependencies(): iterable
    {
        yield 'publisher' => ['publisher'];
        yield 'clock' => ['clock'];
        yield 'sequenceAllocator' => ['sequenceAllocator'];
        yield 'snapshotSerializer' => ['snapshotSerializer'];
        yield 'identifierGenerator' => ['identifierGenerator'];
    }

    private function buildMissing(string $skip): void
    {
        $builder = RunItBuilder::create();
        if ($skip !== 'publisher') {
            $builder = $builder->withPublisher(new InMemoryArtifactPublisher());
        }

        if ($skip !== 'clock') {
            $builder = $builder->withClock(new SystemMonotonicClock());
        }

        if ($skip !== 'sequenceAllocator') {
            $builder = $builder->withSequenceAllocator(new InMemorySequenceAllocator());
        }

        if ($skip !== 'snapshotSerializer') {
            $builder = $builder->withSnapshotSerializer(new JsonSnapshotSerializer());
        }

        if ($skip !== 'identifierGenerator') {
            $builder = $builder->withIdentifierGenerator(new RandomIdentifierGenerator());
        }

        $builder->build();
    }
}
