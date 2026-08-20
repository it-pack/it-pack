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
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
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
 * policies, throwable mapper, hashing, and factories) and composes redaction and sampling around
 * the configured publisher. Required ports are validated at {@see self::build()}.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RunItBuilder implements RunItBuilderInterface
{
    private function __construct(
        private readonly ?ArtifactPublisherInterface $publisher,
        private readonly ?MonotonicClockInterface $clock,
        private readonly ?SequenceAllocatorInterface $allocator,
        private readonly ?SerializerInterface $serializer,
        private readonly ?IdentifierGeneratorInterface $generator,
        private readonly ?ContextStorageInterface $contextStorage,
        private readonly ?SnapshotStoreInterface $snapshotStore,
        private readonly ?ThrowableMapperInterface $throwableMapper,
        private readonly ?NestedExecutionPolicyInterface $nestedPolicy,
        private readonly ?ExecutionCompletionPolicyInterface $completionPolicy,
        private readonly ?RedactorInterface $redactor,
        private readonly ?SamplingPolicyInterface $samplingPolicy,
        private readonly ?ExecutionEngineInterface $engine,
    ) {
    }

    public static function create(): RunItBuilderInterface
    {
        return new self(null, null, null, null, null, null, null, null, null, null, null, null, null);
    }

    public function withPublisher(ArtifactPublisherInterface $publisher): RunItBuilderInterface
    {
        return new self(
            $publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withClock(MonotonicClockInterface $clock): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withMonotonicClock(MonotonicClockInterface $clock): RunItBuilderInterface
    {
        return $this->withClock($clock);
    }

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withSnapshotSerializer(SerializerInterface $serializer): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withContextStorage(ContextStorageInterface $storage): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $storage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withSnapshotStore(?SnapshotStoreInterface $store): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $store,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withThrowableMapper(ThrowableMapperInterface $mapper): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $mapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withRedactor(?RedactorInterface $redactor): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withSamplingPolicy(?SamplingPolicyInterface $policy): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $policy,
            $this->engine,
        );
    }

    public function withNestedExecutionPolicy(NestedExecutionPolicyInterface $policy): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $policy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withCompletionPolicy(ExecutionCompletionPolicyInterface $policy): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $policy,
            $this->redactor,
            $this->samplingPolicy,
            $this->engine,
        );
    }

    public function withExecutionEngine(ExecutionEngineInterface $engine): RunItBuilderInterface
    {
        return new self(
            $this->publisher,
            $this->clock,
            $this->allocator,
            $this->serializer,
            $this->generator,
            $this->contextStorage,
            $this->snapshotStore,
            $this->throwableMapper,
            $this->nestedPolicy,
            $this->completionPolicy,
            $this->redactor,
            $this->samplingPolicy,
            $engine,
        );
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

        $hashCalculator = new DeterministicHashCalculator();
        $executionIdFactory = new StringIdentifierFactory(ExecutionId::fromString(...), $generator);
        $activityIdFactory = new StringIdentifierFactory(ActivityId::fromString(...), $generator);
        $observationIdFactory = new StringIdentifierFactory(ObservationId::fromString(...), $generator);
        $occurrenceIdFactory = new StringIdentifierFactory(OccurrenceId::fromString(...), $generator);
        $snapshotIdFactory = new StringIdentifierFactory(SnapshotId::fromString(...), $generator);

        $executionFactory = new ExecutionFactory();
        $coordinator = new ExecutionLifecycleCoordinator();
        $snapshotFactory = new ExecutionSnapshotFactory($snapshotIdFactory, $hashCalculator);
        $currentExecution = new CurrentExecution($contextStorage);

        $composedPublisher = $publisher;

        if ($this->redactor !== null) {
            $composedPublisher = new RedactingArtifactPublisher($this->redactor, $composedPublisher);
        }

        if ($this->samplingPolicy !== null) {
            $composedPublisher = new SamplingArtifactPublisher($this->samplingPolicy, $composedPublisher);
        }

        $runner = new DefaultExecutionRunner(
            $currentExecution,
            $contextStorage,
            $composedPublisher,
            $serializer,
            $executionFactory,
            $coordinator,
            $snapshotFactory,
            $nestedPolicy,
            $completionPolicy,
            $clock,
            $allocator,
            $executionIdFactory,
            $activityIdFactory,
            $observationIdFactory,
            $occurrenceIdFactory,
            $snapshotIdFactory,
            $hashCalculator,
            $throwableMapper,
            null,
            $this->snapshotStore,
            null,
            $this->engine,
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
