<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\DefaultMeasurementInstrumentMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\BoundedArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\Policy\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
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
    private ArtifactPublisherInterface $boundedPublisher;
    private ExecutionArtifactInterface $snapshotArtifact;
    private ExecutionArtifactInterface $measurementArtifact;
    private DefaultMeasurementInstrumentMapper $otelNullProviderMapper;
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
                ActivityDefinition::create(
                    ActivityName::fromString('load'),
                    ActivityKind::fromString('io'),
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

    /**
     * The bounded publisher is the runner's last gate before any adapter: this
     * subject pins the cost of its count/payload checks per publish.
     */
    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchBoundedPublish(): void
    {
        $this->boundedPublisher->publish($this->snapshotArtifact);
    }

    /**
     * Pins the OpenTelemetry bridge's no-op path: without a meter provider the
     * instrument mapper must short-circuit to a single null check (Phase 3,
     * T3.5 measured this instead of adding speculative lazy initialization).
     */
    #[BeforeMethods('setUp')]
    #[Subject]
    public function benchOtelNullProviderRecord(): void
    {
        $this->otelNullProviderMapper->record($this->measurementArtifact);
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
        $this->boundedPublisher = new BoundedArtifactPublisher(
            new SecurityLimits(),
            $inner,
        );

        $this->otelNullProviderMapper = new DefaultMeasurementInstrumentMapper(
            null,
            new InstrumentCache(64),
            [],
        );
        $this->measurementArtifact = \ItPack\RunIt\Domain\Measurement\Measurement::counter(
            \ItPack\RunIt\Domain\Measurement\MeasurementName::fromString('bench.counter'),
            ExecutionId::fromString('exec-bench'),
            1,
            null,
            \ItPack\RunIt\Domain\Shared\Instant::of(1000),
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
