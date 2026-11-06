<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use Closure;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Application\Factory\ExecutionFactory;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use Throwable;

/**
 * Assembles a fully wired DefaultExecutionRunner for application-level tests.
 *
 * It uses in-memory and deterministic collaborators so tests are fast, isolated, and free of
 * real randomness, clocks, or network effects. Tests may inspect {@see self::$publisher} to
 * assert on emitted artifacts and {@see self::$storage} for context-storage behavior.
 *
 * This is a test double: it is not part of the production runtime.
 */
final class RunItTestKit
{
    public ArtifactPublisherInterface $publisher;

    public DeterministicMonotonicClock $clock;

    public ContextStorageInterface $storage;

    public SequenceAllocatorInterface $allocator;

    public DefaultExecutionRunner $runner;

    private readonly DeterministicHashCalculator $hashCalculator;

    private readonly ThrowableMapperInterface $throwableMapper;

    /** @var StringIdentifierFactoryInterface<ExecutionIdInterface> */
    private readonly StringIdentifierFactoryInterface $executionIdFactory;

    /** @var StringIdentifierFactoryInterface<ActivityIdInterface> */
    private readonly StringIdentifierFactoryInterface $activityIdFactory;

    /** @var StringIdentifierFactoryInterface<ObservationIdInterface> */
    private readonly StringIdentifierFactoryInterface $observationIdFactory;

    /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
    private readonly StringIdentifierFactoryInterface $occurrenceIdFactory;

    /** @var StringIdentifierFactoryInterface<SnapshotIdInterface> */
    private readonly StringIdentifierFactoryInterface $snapshotIdFactory;

    /** @var array<string, SequenceIdentifierGenerator> */
    private array $generators = [];

    /**
     * @param Closure(Throwable): void|null $onPublicationFailure
     * @param ContextStorageInterface|null $storage overrides the default synchronous stack storage
     */
    public function __construct(
        ?ArtifactPublisherInterface $publisher = null,
        private readonly ?Closure $onPublicationFailure = null,
        public ?SnapshotStoreInterface $snapshotStore = null,
        ?Closure $onSnapshotStoreFailure = null,
        ?ContextStorageInterface $storage = null,
        ?SequenceAllocatorInterface $sequenceAllocator = null,
        ?ExecutionCompletionPolicyInterface $completionPolicy = null,
        ?ExecutionLifecycleCoordinatorInterface $coordinator = null,
        ?ThrowableMapperInterface $throwableMapper = null,
        ?SecurityLimits $limits = null,
        ?NestedExecutionPolicyInterface $nestedPolicy = null,
    ) {
        $this->publisher = $publisher ?? new InMemoryArtifactPublisher();
        $this->clock = new DeterministicMonotonicClock(1_000, 0);
        $this->storage = $storage ?? new StackContextStorage();
        $this->allocator = $sequenceAllocator ?? new InMemorySequenceAllocator();
        $this->hashCalculator = new DeterministicHashCalculator();
        $this->throwableMapper = $throwableMapper ?? new DefaultThrowableMapper();

        $this->executionIdFactory = new StringIdentifierFactory(ExecutionId::fromString(...), $this->generator('exec'));
        $this->activityIdFactory = new StringIdentifierFactory(ActivityId::fromString(...), $this->generator('act'));
        $this->observationIdFactory = new StringIdentifierFactory(
            ObservationId::fromString(...),
            $this->generator('obs')
        );
        $this->occurrenceIdFactory = new StringIdentifierFactory(
            OccurrenceId::fromString(...),
            $this->generator('occ')
        );
        $this->snapshotIdFactory = new StringIdentifierFactory(SnapshotId::fromString(...), $this->generator('snap'));

        $this->runner = new DefaultExecutionRunner(
            new CurrentExecution($this->storage),
            $this->storage,
            $this->publisher,
            new JsonSnapshotSerializer(),
            new ExecutionFactory(),
            $coordinator ?? new ExecutionLifecycleCoordinator(),
            new ExecutionSnapshotFactory($this->snapshotIdFactory, $this->hashCalculator),
            $nestedPolicy ?? new ChildExecutionPolicy(),
            $completionPolicy ?? new AutoFailOpenActivitiesPolicy(),
            $this->clock,
            $this->allocator,
            $this->executionIdFactory,
            $this->activityIdFactory,
            $this->observationIdFactory,
            $this->occurrenceIdFactory,
            $this->snapshotIdFactory,
            $this->hashCalculator,
            $this->throwableMapper,
            $this->onPublicationFailure,
            $this->snapshotStore,
            $onSnapshotStoreFailure,
            limits: $limits ?? new SecurityLimits(),
        );
    }

    /**
     * Builds an execution scope bound to this kit's collaborators.
     *
     * Used by context-storage tests that need real {@see ExecutionScope} instances without going
     * through the runner.
     */
    public function buildScope(ExecutionInterface $execution, ExecutionContextInterface $context): ExecutionScope
    {
        return new ExecutionScope(
            $execution,
            $execution->executionId(),
            $context,
            $this->allocator,
            $this->clock,
            new FailSafeArtifactPublisher(
                $this->publisher,
                new FailOpenPolicy(),
                $this->onPublicationFailure instanceof Closure ? new CallbackHealthReporter($this->onPublicationFailure) : null,
            ),
            new JsonSnapshotSerializer(),
            $this->throwableMapper,
            $this->observationIdFactory,
            $this->occurrenceIdFactory,
            $this->snapshotIdFactory,
            $this->activityIdFactory,
            $this->hashCalculator,
        );
    }

    private function generator(string $prefix): IdentifierGeneratorInterface
    {
        return $this->generators[$prefix] ??= new SequenceIdentifierGenerator($prefix);
    }

    /**
     * @return list<ExecutionArtifactInterface>
    >
     */
    public function artifacts(): array
    {
        if ($this->publisher instanceof InMemoryArtifactPublisher) {
            return $this->publisher->published();
        }

        return [];
    }

    /**
     * @return list<SnapshotInterface>
     */
    public function storedSnapshots(): array
    {
        if ($this->snapshotStore instanceof InMemorySnapshotStore) {
            return $this->snapshotStore->stored();
        }

        return [];
    }
}
