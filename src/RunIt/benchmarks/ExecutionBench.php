<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M12.5 — production-relevant benchmarks for the RunIt execution hot path.
 *
 * These measure the latency and throughput of representative operations so that
 * the performance gates (M12.6) have machine-readable baselines to compare
 * against. They are intentionally thin wrappers around real collaborators and
 * avoid network, filesystem, and randomness.
 */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(100)]
final class ExecutionBench
{
    private RunItTestKit $kit;
    private ArtifactPublisherInterface $redactorChain;
    private ExecutionArtifactInterface $snapshotArtifact;
    private DefaultThrowableMapper $mapper;
    private ExecutionDefinition $definition;

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchNullWrapper(): void
    {
        $value = static fn () => 1;
        $value();
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchInMemoryExecutionLifecycle(): void
    {
        $this->kit->runner->run($this->definition, static fn (): int => 1);
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchActivityLifecycle(): void
    {
        $this->kit->runner->run($this->definition, function ($scope) {
            return $scope->activities()->run(
                \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
                    \ItPack\RunIt\Domain\Activity\ActivityName::fromString('load'),
                    \ItPack\RunIt\Domain\Activity\ActivityKind::fromString('io'),
                ),
                static fn () => 'inner',
            );
        });
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchMeasurementCreation(): void
    {
        $this->kit->runner->run($this->definition, static function ($scope) {
            $scope->measurements()->counter('items', 1);

            return null;
        });
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchObservationCreation(): void
    {
        $this->kit->runner->run($this->definition, static function ($scope) {
            $scope->observations()->record('observed', ObservationSeverity::Info);

            return null;
        });
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchThrowableNormalization(): void
    {
        $this->mapper->describe(new \RuntimeException('boom', 42));
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchRedaction(): void
    {
        $this->redactorChain->publish($this->snapshotArtifact);
    }

    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchBuffering(): void
    {
        $publisher = new InMemoryArtifactPublisher();

        for ($i = 0; $i < 200; ++$i) {
            $publisher->publish($this->snapshotArtifact);
        }
    }

    public function setUp(): void
    {
        $this->kit = new RunItTestKit(storage: new StackContextStorage());
        $this->definition = ExecutionDefinition::create(
            ExecutionName::fromString('bench'),
            ExecutionKind::fromString('task'),
        );
        $this->mapper = new DefaultThrowableMapper();

        $inner = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor();
        $this->redactorChain = new SamplingArtifactPublisher(
            new AlwaysSamplePolicy(),
            new RedactingArtifactPublisher($redactor, $inner),
        );

        $this->snapshotArtifact = Snapshot::create(
            SnapshotId::fromString('snap-bench'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['password' => 'secret', 'status' => 'ok'],
            new DeterministicHashCalculator(),
        );
    }
}
