<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Publication\BoundedArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/**
 * Default lifecycle-safe execution runner.
 *
 * The runner validates and resolves the execution definition (applying the configured nested
 * policy), allocates identity and sequence, attaches an ambient scope, invokes the operation,
 * records the outcome, and always restores the prior context. On success it returns the
 * operation's result unchanged; on failure it records the failure and rethrows the exact
 * original throwable. Publication failures are caught and forwarded to the configured health
 * callback so they can never replace the business result or exception.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultExecutionRunner implements ExecutionRunnerInterface
{
    use InstantClockBehavior;

    /**
     * @param Closure(\Throwable): void|null $onPublicationFailure receives publication errors so they can be reported without replacing the business result
     */
    private FailSafeArtifactPublisher $safePublisher;
    public function __construct(
        private CurrentExecutionInterface              $currentExecution,
        private ContextStorageInterface                $contextStorage,
        private ArtifactPublisherInterface             $publisher,
        private SerializerInterface                    $serializer,
        private ExecutionFactoryInterface              $executionFactory,
        private ExecutionLifecycleCoordinatorInterface $coordinator,
        private ExecutionSnapshotFactoryInterface      $snapshotFactory,
        private NestedExecutionPolicyInterface         $nestedPolicy,
        private ExecutionCompletionPolicyInterface     $completionPolicy,
        private MonotonicClockInterface                $clock,
        private SequenceAllocatorInterface             $sequenceAllocator,
        /** @var StringIdentifierFactoryInterface<ExecutionIdInterface> */
        private StringIdentifierFactoryInterface       $executionIdFactory,
        /** @var StringIdentifierFactoryInterface<ActivityIdInterface> */
        private StringIdentifierFactoryInterface       $activityIdFactory,
        /** @var StringIdentifierFactoryInterface<ObservationIdInterface> */
        private StringIdentifierFactoryInterface       $observationIdFactory,
        /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
        private StringIdentifierFactoryInterface       $occurrenceIdFactory,
        /** @var StringIdentifierFactoryInterface<SnapshotIdInterface> */
        private StringIdentifierFactoryInterface       $snapshotIdFactory,
        private HashCalculatorInterface                $hashCalculator,
        private ThrowableMapperInterface               $throwableMapper,
        private ?Closure                               $onPublicationFailure = null,
        private ?SnapshotStoreInterface                $snapshotStore = null,
        private ?Closure                               $onSnapshotStoreFailure = null,
        private ?ExecutionEngineInterface              $engine = null,
        private SecurityLimits                         $limits = new SecurityLimits(),
    ) {
        $health = $this->onPublicationFailure === null ? null : new CallbackHealthReporter($this->onPublicationFailure);
        $bounded = new BoundedArtifactPublisher($this->limits, $this->publisher);
        $this->safePublisher = new FailSafeArtifactPublisher($bounded, new FailOpenPolicy(), $health);
    }

    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        $current = $this->currentExecution->get();
        $mode = $this->nestedPolicy->resolve($current, $definition);

        return match ($mode) {
            NestedExecutionMode::RejectNested => throw RejectedNestedExecutionException::forExecution(
                $current?->execution()->id()->toString() ?? 'unknown',
            ),
            NestedExecutionMode::ActivityOnly => $this->runAsActivity($current, $definition, $operation),
            default => $this->runExecution($mode, $current, $definition, $operation),
        };
    }

    private function runAsActivity(?ExecutionScopeInterface $current, ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        if ($current === null) {
            return $this->runExecution(NestedExecutionMode::ChildExecution, null, $definition, $operation);
        }

        $activityDefinition = ActivityDefinition::create(
            \ItPack\RunIt\Domain\Activity\ActivityName::fromString($definition->name()->toString()),
            \ItPack\RunIt\Domain\Activity\ActivityKind::fromString($definition->kind()->toString()),
        );

        return $current->activities()->run($activityDefinition, $operation);
    }

    private function runExecution(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
        ExecutionDefinitionInterface $definition,
        callable $operation,
    ): mixed {
        $parentId = ($mode === NestedExecutionMode::ChildExecution && $current !== null) ? $current->execution()->id() : null;
        $linkedIds = ($mode === NestedExecutionMode::DetachedExecution && $current !== null) ? [$current->execution()->id()] : [];

        $effectiveDefinition = ExecutionDefinition::create(
            $definition->name(),
            $definition->kind(),
            $parentId,
            $linkedIds,
            $definition->context(),
        );

        $rawId = $this->executionIdFactory->build();
        $id = ExecutionId::fromString($rawId->toString());
        $startedAt = $this->now();
        $sequence = $this->sequenceAllocator->allocate($id);
        $created = $this->executionFactory->create($effectiveDefinition, $id, $startedAt, $sequence);
        $execution = $this->coordinator->start($created);

        $scope = new ExecutionScope(
            $execution,
            $id,
            $effectiveDefinition->context(),
            $this->sequenceAllocator,
            $this->clock,
            $this->safePublisher,
            $this->serializer,
            $this->throwableMapper,
            $this->observationIdFactory,
            $this->occurrenceIdFactory,
            $this->snapshotIdFactory,
            $this->activityIdFactory,
            $this->hashCalculator,
            $this->snapshotStore,
            $this->onSnapshotStoreFailure,
            $this->limits,
        );

        $token = $this->contextStorage->attach($scope);

        try {
            $this->safePublish($execution);

            $result = $this->engine()->run($scope, $operation);

            $this->completeSuccess($execution, $scope);

            return $result;
        } catch (Throwable $throwable) {
            $this->completeFailure($execution, $scope, $throwable);

            throw $throwable;
        } finally {
            $this->detachSafely($token);
        }
    }

    /**
     * @throws LifecycleExceptionInterface
     */
    private function completeSuccess(ExecutionInterface $execution, ExecutionScope $scope): void
    {
        $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());

        $completed = $this->coordinator->transition($execution, ExecutionSuccess::create(), $this->now());

        $this->publishTerminal($completed);
    }

    /**
     * @throws LifecycleExceptionInterface
     */
    private function completeFailure(ExecutionInterface $execution, ExecutionScope $scope, Throwable $throwable): void
    {
        $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());

        $description = $this->throwableMapper->describe($throwable);
        $scope->observations()->record(
            'Execution failed: ' . $description->message(),
            ObservationSeverity::Error,
            null,
            $throwable,
            'execution.failure',
        );

        $completed = $this->coordinator->transition($execution, ExecutionFailure::of(ExecutionStatus::Failed, $description), $this->now());

        $this->publishTerminal($completed);
    }

    /**
     * Publishes the completed execution and its terminal snapshot, isolating any
     * post-completion failure (serialization, hashing, or publication) so it can
     * never replace the original business result or throwable.
     */
    private function publishTerminal(ExecutionInterface $completed): void
    {
        try {
            $snapshot = $this->snapshotFactory->terminal($completed);
        } catch (Throwable $failure) {
            $this->reportFailure($failure);

            return;
        }

        try {
            $this->safePublish($completed);
            $this->safePublish($snapshot);
        } catch (Throwable $failure) {
            $this->reportFailure($failure);
        }
    }

    /**
     * Detaches the ambient context in a way that can never mask the original
     * outcome: a detach failure is reported but not rethrown.
     */
    private function detachSafely(ContextTokenInterface $token): void
    {
        try {
            $this->contextStorage->detach($token);
        } catch (Throwable $failure) {
            $this->reportFailure($failure);
        }
    }

    private function reportFailure(Throwable $failure): void
    {
        if ($this->onPublicationFailure instanceof Closure) {
            ($this->onPublicationFailure)($failure);
        }
    }

    private function engine(): ExecutionEngineInterface
    {
        return $this->engine ?? new SynchronousExecutionEngine();
    }

    /**
     * @throws Throwable
     */
    private function safePublish(ExecutionArtifactInterface $artifact): void
    {
        $this->safePublisher->publish($artifact);
    }
}
