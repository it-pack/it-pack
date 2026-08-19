<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\Builder\RunItBuilderInterface;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use ItPack\RunIt\SDK\RunItInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
