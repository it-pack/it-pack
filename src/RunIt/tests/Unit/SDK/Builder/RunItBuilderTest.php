<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\ExecutionFactory;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
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
        return ExecutionDefinition::create(ExecutionName::fromString('builder.spec'), ExecutionKind::fromString('operation'));
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

    public function testSamplingPolicyDropsArtifacts(): void
    {
        $spy = new SpyPublisher();
        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSamplingPolicy(new NeverSamplePolicySpy())
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        self::assertSame([], $spy->published);
    }

    public function testRedactorWrapsPublishedArtifacts(): void
    {
        $spy = new SpyPublisher();
        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withRedactor(new TaggingRedactor())
            ->build()
        ;

        $runtime->run($this->definition(), static fn (): int => 1);

        foreach ($spy->published as $artifact) {
            self::assertInstanceOf(RedactedTagArtifact::class, $artifact);
        }
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
    }

    public function testSecurityLimitsAndHandlerSeamsReturnNewBuilderInstances(): void
    {
        $base = $this->builder();

        self::assertNotSame($base, $base->withSecurityLimits(new SecurityLimits()));
        self::assertNotSame(
            $base,
            $base->withPublicationFailureHandler(static function (\Throwable $failure): void {
            }),
        );
        self::assertNotSame(
            $base,
            $base->withSnapshotStoreFailureHandler(static function (\Throwable $failure): void {
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

        self::assertTrue($factory->called, 'build() must compose the publisher stack through the configured factory');
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
            ->withPublicationFailureHandler(static function (\Throwable $failure) use (&$failures): void {
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
        self::assertNotEmpty($failures, 'bound-exceeded drops must be observable through the publication handler');

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
        $spy = new SpyPublisher();

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
            $runtime->run($this->definition(), static function () use ($thrown): void {
                throw $thrown;
            });
        } catch (RuntimeException) {
            // business exception surfaces unchanged; the recorded observation is
            // what we are inspecting here
        }

        $redacted = array_filter($spy->published, static fn ($a) => $a instanceof RedactedArtifact);
        self::assertNotEmpty($redacted, 'without withRedactor() the default redactor must mask throwable messages');
    }

    public function testExplicitNullRedactorOptOutPublishesUnredacted(): void
    {
        $spy = new SpyPublisher();

        $runtime = RunItBuilder::create()
            ->withPublisher($spy)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withRedactor(null)
            ->build()
        ;

        try {
            $runtime->run($this->definition(), static function (): int {
                throw new RuntimeException('raw observation source');
            });
        } catch (\Throwable) {
        }

        $observations = array_filter(
            $spy->published,
            static fn ($a) => $a instanceof \ItPack\RunIt\Contract\Domain\ObservationInterface,
        );
        self::assertNotEmpty($observations, 'an explicit withRedactor(null) opt-out publishes raw observations');
        self::assertEmpty(
            array_filter($spy->published, static fn ($a) => $a instanceof RedactedArtifact),
            'no artifact may be wrapped by the redactor once opted out',
        );
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
                    ActivityDefinition::create(ActivityName::fromString('outer'), ActivityKind::fromString('task')),
                    static function () use ($scope): void {
                        $scope->activities()->run(
                            ActivityDefinition::create(ActivityName::fromString('inner'), ActivityKind::fromString('task')),
                            static fn (): string => 'never reached',
                        );
                    },
                );
            });

            self::fail('configured maxActivityDepth must be enforced by the built runtime');
        } catch (ActivityDepthExceededException $exception) {
            self::assertStringContainsString('2', $exception->getMessage(), 'depth 2 must exceed the configured limit of 1');
        }
    }

    public function testPublicationFailureHandlerReceivesAdapterFailure(): void
    {
        $failures = [];
        $runtime = RunItBuilder::create()
            ->withPublisher(new FailingPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withPublicationFailureHandler(static function (\Throwable $failure) use (&$failures): void {
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
            ->withSnapshotStore(new FailingSnapshotStore())
            ->withSnapshotStoreFailureHandler(static function (\Throwable $failure) use (&$storeFailures): void {
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
        self::assertTrue($spy->called, 'configured hash calculator must be wired into the default snapshot factory');
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
        self::assertTrue($spy->called, 'configured hash calculator must be invoked through the custom snapshot factory');
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

final class FailingPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new \RuntimeException('publisher is broken');
    }
}

final class FailingSnapshotStore implements SnapshotStoreInterface
{
    public function store(SnapshotInterface $snapshot): void
    {
        throw new \RuntimeException('snapshot store is broken');
    }
}

final class SpyPublisherStackFactory implements PublisherStackFactoryInterface
{
    public bool $called = false;

    public function __construct(private readonly ?ArtifactPublisherInterface $override = null)
    {
    }

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        $this->called = true;

        return $this->override ?? $base;
    }
}

final class SpyPublisher implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    public array $published = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->published[] = $artifact;
    }
}

final class NeverSamplePolicySpy implements \ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface
{
    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        return false;
    }
}

final class TaggingRedactor implements RedactorInterface
{
    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        return new RedactedTagArtifact($artifact);
    }
}

final class RedactedTagArtifact implements ExecutionArtifactInterface
{
    public function __construct(private readonly ExecutionArtifactInterface $inner)
    {
    }

    public function normalize(): array
    {
        return $this->inner->normalize();
    }
}
