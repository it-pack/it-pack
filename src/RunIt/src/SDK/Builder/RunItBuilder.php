<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use Closure;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Factory\ExecutionFactory;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultSdkRedactor;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Application\Publication\ProvenanceArtifactPublisher;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\SDK\DefaultRunIt;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Immutable {@see RunItBuilderInterface} implementation.
 *
 * The builder supplies application and domain defaults (context storage, nested and completion
 * policies, throwable mapper, hashing, execution factory, lifecycle coordinator, snapshot factory,
 * identifier factories, and security limits) and composes redaction and sampling around the
 * configured publisher. Any default collaborator can be replaced through its `with*` seam, and
 * publication/snapshot-store failure handlers can be registered so internal faults stay
 * observable. Required ports are validated at {@see self::build()}.
 *
 * The default context storage is a synchronous stack, not an isolation boundary
 * for interleaved Fibers or coroutines. Configure runtime-appropriate storage
 * with {@see self::withContextStorage()} when executions may overlap.
 *
 * Every `with*` method returns a new builder via copy-on-write (`clone` + assign), so configuration
 * can be shared and extended without mutation.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RunItBuilder implements ExtendedRunItBuilderInterface
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
        private bool $redactorConfigured = false,
        private ?SamplingPolicyInterface $samplingPolicy = null,
        private ?ExecutionEngineInterface $engine = null,
        private ?HashCalculatorInterface $hashCalculator = null,
        private ?ExecutionFactoryInterface $executionFactory = null,
        private ?ExecutionLifecycleCoordinatorInterface $coordinator = null,
        private ?ExecutionSnapshotFactoryInterface $snapshotFactory = null,
        private ?SecurityLimits $limits = null,
        private ?Closure $onPublicationFailure = null,
        private ?Closure $onSnapshotStoreFailure = null,
        private ?PublisherStackFactoryInterface $publisherStackFactory = null,
        private ?Closure $concurrencyValidator = null,
    ) {
    }

    public static function create(): RunItBuilder
    {
        return new self();
    }

    public function withPublisher(ArtifactPublisherInterface $publisher): RunItBuilder
    {
        $clone = clone $this;
        $clone->publisher = $publisher;

        return $clone;
    }

    public function withClock(MonotonicClockInterface $clock): RunItBuilder
    {
        $clone = clone $this;
        $clone->clock = $clock;

        return $clone;
    }

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): RunItBuilder
    {
        $clone = clone $this;
        $clone->allocator = $allocator;

        return $clone;
    }

    public function withSnapshotSerializer(SerializerInterface $serializer): RunItBuilder
    {
        $clone = clone $this;
        $clone->serializer = $serializer;

        return $clone;
    }

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): RunItBuilder
    {
        $clone = clone $this;
        $clone->generator = $generator;

        return $clone;
    }

    public function withContextStorage(ContextStorageInterface $storage): RunItBuilder
    {
        $clone = clone $this;
        $clone->contextStorage = $storage;

        return $clone;
    }

    public function withSnapshotStore(?SnapshotStoreInterface $store): RunItBuilder
    {
        $clone = clone $this;
        $clone->snapshotStore = $store;

        return $clone;
    }

    public function withThrowableMapper(ThrowableMapperInterface $mapper): RunItBuilder
    {
        $clone = clone $this;
        $clone->throwableMapper = $mapper;

        return $clone;
    }

    public function withRedactor(?RedactorInterface $redactor): RunItBuilder
    {
        // Explicit null disables the redactor, not the SDK classification boundary.
        $clone = clone $this;
        $clone->redactor = $redactor;
        $clone->redactorConfigured = true;

        return $clone;
    }

    public function withSamplingPolicy(?SamplingPolicyInterface $policy): RunItBuilder
    {
        $clone = clone $this;
        $clone->samplingPolicy = $policy;

        return $clone;
    }

    public function withNestedExecutionPolicy(NestedExecutionPolicyInterface $policy): RunItBuilder
    {
        $clone = clone $this;
        $clone->nestedPolicy = $policy;

        return $clone;
    }

    public function withCompletionPolicy(ExecutionCompletionPolicyInterface $policy): RunItBuilder
    {
        $clone = clone $this;
        $clone->completionPolicy = $policy;

        return $clone;
    }

    public function withExecutionEngine(ExecutionEngineInterface $engine): RunItBuilder
    {
        $clone = clone $this;
        $clone->engine = $engine;

        return $clone;
    }

    public function withHashCalculator(HashCalculatorInterface $hashCalculator): RunItBuilder
    {
        $clone = clone $this;
        $clone->hashCalculator = $hashCalculator;

        return $clone;
    }

    public function withExecutionFactory(ExecutionFactoryInterface $executionFactory): RunItBuilder
    {
        $clone = clone $this;
        $clone->executionFactory = $executionFactory;

        return $clone;
    }

    public function withLifecycleCoordinator(ExecutionLifecycleCoordinatorInterface $coordinator): RunItBuilder
    {
        $clone = clone $this;
        $clone->coordinator = $coordinator;

        return $clone;
    }

    public function withSnapshotFactory(ExecutionSnapshotFactoryInterface $snapshotFactory): RunItBuilder
    {
        $clone = clone $this;
        $clone->snapshotFactory = $snapshotFactory;

        return $clone;
    }

    public function withSecurityLimits(SecurityLimits $limits): RunItBuilder
    {
        $clone = clone $this;
        $clone->limits = $limits;

        return $clone;
    }

    public function withPublicationFailureHandler(?Closure $handler): RunItBuilder
    {
        $clone = clone $this;
        $clone->onPublicationFailure = $handler;

        return $clone;
    }

    public function withSnapshotStoreFailureHandler(?Closure $handler): RunItBuilder
    {
        $clone = clone $this;
        $clone->onSnapshotStoreFailure = $handler;

        return $clone;
    }

    /**
     * Overrides how the publisher stack is composed around the base publisher.
     * The SDK applies its redactor and classification before the custom stack;
     * custom factories receive null for the redactor argument.
     */
    public function withPublisherStackFactory(PublisherStackFactoryInterface $factory): RunItBuilder
    {
        $clone = clone $this;
        $clone->publisherStackFactory = $factory;

        return $clone;
    }

    public function withConcurrencyValidator(?Closure $validator): RunItBuilder
    {
        $clone = clone $this;
        $clone->concurrencyValidator = $validator;

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

        $health = $this->healthReporter();
        $authority = new ProvenanceAuthority($health);

        $snapshotFactory = $this->snapshotFactory
            ?? new ExecutionSnapshotFactory(
                $snapshotIdFactory,
                $hashCalculator,
                null,
                $authority,
            );

        $currentExecution = new CurrentExecution($contextStorage);

        $engine = $this->engine ?? new SynchronousExecutionEngine();

        $capability = $engine->capability();
        $this->validateConcurrentContextStorage($capability, $contextStorage);
        $this->applyConcurrencyValidator($capability, $contextStorage);

        // Explicit null disables the redactor but retains classification.
        $publisherStackFactory = $this->publisherStackFactory ?? new PublisherStackFactory();
        $redactor = $this->resolveRedactor();

        $composedPublisher = $this->composePublisherStack(
            $publisher,
            $redactor,
            $health,
            $authority,
            $publisherStackFactory,
        );

        $composedPublisher = $this->wrapPublisherStackForCustomFactory(
            $composedPublisher,
            $redactor,
            $health,
            $authority,
            $publisherStackFactory,
        );

        $composedPublisher = new ProvenanceArtifactPublisher($authority, $composedPublisher);

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
            onPublicationFailure: $this->onPublicationFailure,
            snapshotStore: $this->snapshotStore,
            onSnapshotStoreFailure: $this->onSnapshotStoreFailure,
            engine: $engine,
            limits: $this->limits ?? new SecurityLimits(),
            authority: $authority,
        );

        return new DefaultRunIt($runner, $currentExecution);
    }

    private function validateConcurrentContextStorage(
        ?EngineCapabilityInterface $capability,
        ContextStorageInterface $contextStorage,
    ): void {
        if ($capability instanceof EngineCapability
            && $capability->concurrent
            && $contextStorage instanceof StackContextStorage
        ) {
            throw IncompatibleContextStorageException::sharedStackWithConcurrentEngine();
        }
    }

    private function applyConcurrencyValidator(
        ?EngineCapabilityInterface $capability,
        ContextStorageInterface $contextStorage,
    ): void {
        if ($this->concurrencyValidator instanceof Closure) {
            ($this->concurrencyValidator)($capability, $contextStorage);
        }
    }

    private function composePublisherStack(
        ArtifactPublisherInterface $publisher,
        ?RedactorInterface $redactor,
        InternalHealthReporterInterface $health,
        ProvenanceAuthority $authority,
        PublisherStackFactoryInterface $publisherStackFactory,
    ): ArtifactPublisherInterface {
        return match (true) {
            $publisherStackFactory instanceof PublisherStackFactory => $publisherStackFactory->compose(
                $publisher,
                $redactor,
                $this->samplingPolicy,
                $health,
                $this->limits,
                $authority,
            ),
            $publisherStackFactory instanceof HealthAwarePublisherStackFactoryInterface =>
                $publisherStackFactory->composeWithHealth(
                    $publisher,
                    null,
                    $this->samplingPolicy,
                    $health,
                ),
            default => $publisherStackFactory->compose($publisher, null, $this->samplingPolicy),
        };
    }

    private function wrapPublisherStackForCustomFactory(
        ArtifactPublisherInterface $composedPublisher,
        ?RedactorInterface $redactor,
        InternalHealthReporterInterface $health,
        ProvenanceAuthority $authority,
        PublisherStackFactoryInterface $publisherStackFactory,
    ): ArtifactPublisherInterface {
        if (!$publisherStackFactory instanceof PublisherStackFactory) {
            return new PublisherStackFactory()->compose(
                $composedPublisher,
                $redactor,
                null,
                $health,
                $this->limits,
                $authority,
            );
        }

        return $composedPublisher;
    }

    private function resolveRedactor(): ?RedactorInterface
    {
        if ($this->redactorConfigured) {
            return $this->redactor;
        }

        return new DefaultSdkRedactor();
    }

    private function healthReporter(): InternalHealthReporterInterface
    {
        return $this->onPublicationFailure instanceof Closure
            ? new CallbackHealthReporter($this->onPublicationFailure)
            : new ErrorLogHealthReporter();
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
