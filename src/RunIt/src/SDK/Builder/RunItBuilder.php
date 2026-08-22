<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use ItPack\RunIt\Application\Factory\ExecutionFactory;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\SDK\DefaultRunIt;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Immutable {@see RunItBuilderInterface} implementation.
 *
 * The builder supplies application and domain defaults (context storage, nested and completion
 * policies, throwable mapper, hashing, execution factory, lifecycle coordinator, snapshot factory,
 * and identifier factories) and composes redaction and sampling around the configured publisher.
 * Any default collaborator can be replaced through its `with*` seam. Required ports are validated at
 * {@see self::build()}.
 *
 * Every `with*` method returns a new builder via copy-on-write (`clone` + assign), so configuration
 * can be shared and extended without mutation.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RunItBuilder implements RunItBuilderInterface
{
    private function __construct(
        private ?ArtifactPublisherInterface $publisher = null,
        private ?MonotonicClockInterface $clock = null,
        private ?SequenceAllocatorInterface $allocator = null,
        private ?SerializerInterface $serializer = null,
        private ?IdentifierGeneratorInterface $generator = null,
        private ?ContextStorageInterface $contextStorage = null,
        private ?SnapshotStoreInterface $snapshotStore = null,
        private ?ThrowableMapperInterface $throwableMapper = null,
        private ?NestedExecutionPolicyInterface $nestedPolicy = null,
        private ?ExecutionCompletionPolicyInterface $completionPolicy = null,
        private ?RedactorInterface $redactor = null,
        private ?SamplingPolicyInterface $samplingPolicy = null,
        private ?ExecutionEngineInterface $engine = null,
        private ?HashCalculatorInterface $hashCalculator = null,
        private ?ExecutionFactoryInterface $executionFactory = null,
        private ?ExecutionLifecycleCoordinatorInterface $coordinator = null,
        private ?ExecutionSnapshotFactoryInterface $snapshotFactory = null,
    ) {
    }

    public static function create(): RunItBuilderInterface
    {
        return new self();
    }

    public function withPublisher(ArtifactPublisherInterface $publisher): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->publisher = $publisher;

        return $clone;
    }

    public function withClock(MonotonicClockInterface $clock): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->clock = $clock;

        return $clone;
    }

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->allocator = $allocator;

        return $clone;
    }

    public function withSnapshotSerializer(SerializerInterface $serializer): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->serializer = $serializer;

        return $clone;
    }

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->generator = $generator;

        return $clone;
    }

    public function withContextStorage(ContextStorageInterface $storage): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->contextStorage = $storage;

        return $clone;
    }

    public function withSnapshotStore(?SnapshotStoreInterface $store): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->snapshotStore = $store;

        return $clone;
    }

    public function withThrowableMapper(ThrowableMapperInterface $mapper): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->throwableMapper = $mapper;

        return $clone;
    }

    public function withRedactor(?RedactorInterface $redactor): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->redactor = $redactor;

        return $clone;
    }

    public function withSamplingPolicy(?SamplingPolicyInterface $policy): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->samplingPolicy = $policy;

        return $clone;
    }

    public function withNestedExecutionPolicy(NestedExecutionPolicyInterface $policy): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->nestedPolicy = $policy;

        return $clone;
    }

    public function withCompletionPolicy(ExecutionCompletionPolicyInterface $policy): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->completionPolicy = $policy;

        return $clone;
    }

    public function withExecutionEngine(ExecutionEngineInterface $engine): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->engine = $engine;

        return $clone;
    }

    public function withHashCalculator(HashCalculatorInterface $hashCalculator): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->hashCalculator = $hashCalculator;

        return $clone;
    }

    public function withExecutionFactory(ExecutionFactoryInterface $executionFactory): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->executionFactory = $executionFactory;

        return $clone;
    }

    public function withLifecycleCoordinator(ExecutionLifecycleCoordinatorInterface $coordinator): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->coordinator = $coordinator;

        return $clone;
    }

    public function withSnapshotFactory(ExecutionSnapshotFactoryInterface $snapshotFactory): RunItBuilderInterface
    {
        $clone = clone $this;
        $clone->snapshotFactory = $snapshotFactory;

        return $clone;
    }

    public function build(): RunItInterface
    {
        $publisher = $this->requirePublisher();
        $clock = $this->requireClock();
        $allocator = $this->requireAllocator();
        $serializer = $this->requireSerializer();
        $generator = $this->requireGenerator();

        $contextStorage = $this->contextStorage ?? new StackContextStorage();
        $throwableMapper = $this->throwableMapper ?? new DefaultThrowableMapper();
        $nestedPolicy = $this->nestedPolicy ?? new ChildExecutionPolicy();
        $completionPolicy = $this->completionPolicy ?? new AutoFailOpenActivitiesPolicy();

        $hashCalculator = $this->hashCalculator ?? new DeterministicHashCalculator();
        $executionIdFactory = new StringIdentifierFactory(ExecutionId::fromString(...), $generator);
        $activityIdFactory = new StringIdentifierFactory(ActivityId::fromString(...), $generator);
        $observationIdFactory = new StringIdentifierFactory(ObservationId::fromString(...), $generator);
        $occurrenceIdFactory = new StringIdentifierFactory(OccurrenceId::fromString(...), $generator);
        $snapshotIdFactory = new StringIdentifierFactory(SnapshotId::fromString(...), $generator);

        $executionFactory = $this->executionFactory ?? new ExecutionFactory();
        $coordinator = $this->coordinator ?? new ExecutionLifecycleCoordinator();
        $snapshotFactory = $this->snapshotFactory ?? new ExecutionSnapshotFactory($snapshotIdFactory, $hashCalculator);

        $currentExecution = new CurrentExecution($contextStorage);

        $composedPublisher = $publisher;

        if ($this->redactor !== null) {
            $composedPublisher = new RedactingArtifactPublisher($this->redactor, $composedPublisher);
        }

        if ($this->samplingPolicy !== null) {
            $composedPublisher = new SamplingArtifactPublisher($this->samplingPolicy, $composedPublisher);
        }

        $runner = new DefaultExecutionRunner(
            currentExecution: $currentExecution,
            contextStorage: $contextStorage,
            publisher: $composedPublisher,
            serializer: $serializer,
            executionFactory: $executionFactory,
            coordinator: $coordinator,
            snapshotFactory: $snapshotFactory,
            nestedPolicy: $nestedPolicy,
            completionPolicy: $completionPolicy,
            clock: $clock,
            sequenceAllocator: $allocator,
            executionIdFactory: $executionIdFactory,
            activityIdFactory: $activityIdFactory,
            observationIdFactory: $observationIdFactory,
            occurrenceIdFactory: $occurrenceIdFactory,
            snapshotIdFactory: $snapshotIdFactory,
            hashCalculator: $hashCalculator,
            throwableMapper: $throwableMapper,
            onPublicationFailure: null,
            snapshotStore: $this->snapshotStore,
            onSnapshotStoreFailure: null,
            engine: $this->engine,
        );

        return new DefaultRunIt($runner, $currentExecution);
    }

    private function requirePublisher(): ArtifactPublisherInterface
    {
        return $this->publisher ?? throw MissingBuilderDependencyException::forDependency('publisher');
    }

    private function requireClock(): MonotonicClockInterface
    {
        return $this->clock ?? throw MissingBuilderDependencyException::forDependency('clock');
    }

    private function requireAllocator(): SequenceAllocatorInterface
    {
        return $this->allocator ?? throw MissingBuilderDependencyException::forDependency('sequenceAllocator');
    }

    private function requireSerializer(): SerializerInterface
    {
        return $this->serializer ?? throw MissingBuilderDependencyException::forDependency('snapshotSerializer');
    }

    private function requireGenerator(): IdentifierGeneratorInterface
    {
        return $this->generator ?? throw MissingBuilderDependencyException::forDependency('identifierGenerator');
    }
}
