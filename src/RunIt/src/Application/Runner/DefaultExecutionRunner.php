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
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
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
use ItPack\RunIt\Domain\Shared\Instant;
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
final class DefaultExecutionRunner implements ExecutionRunnerInterface
{
    /**
     * @param Closure(\Throwable): void|null $onPublicationFailure receives publication errors so they can be reported without replacing the business result
     */
    private readonly FailSafeArtifactPublisher $safePublisher;
    public function __construct(
        private readonly CurrentExecutionInterface $currentExecution,
        private readonly ContextStorageInterface $contextStorage,
        private readonly ArtifactPublisherInterface $publisher,
        private readonly SerializerInterface $serializer,
        private readonly ExecutionFactoryInterface $executionFactory,
        private readonly ExecutionLifecycleCoordinatorInterface $coordinator,
        private readonly ExecutionSnapshotFactoryInterface $snapshotFactory,
        private readonly NestedExecutionPolicyInterface $nestedPolicy,
        private readonly ExecutionCompletionPolicyInterface $completionPolicy,
        private readonly MonotonicClockInterface $clock,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ExecutionIdInterface> */
        private readonly StringIdentifierFactoryInterface $executionIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ActivityIdInterface> */
        private readonly StringIdentifierFactoryInterface $activityIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ObservationIdInterface> */
        private readonly StringIdentifierFactoryInterface $observationIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\OccurrenceIdInterface> */
        private readonly StringIdentifierFactoryInterface $occurrenceIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\SnapshotIdInterface> */
        private readonly StringIdentifierFactoryInterface $snapshotIdFactory,
        private readonly HashCalculatorInterface $hashCalculator,
        private readonly ThrowableMapperInterface $throwableMapper,
        private readonly ?Closure $onPublicationFailure = null,
        private readonly ?SnapshotStoreInterface $snapshotStore = null,
        private readonly ?Closure $onSnapshotStoreFailure = null,
        private readonly ?ExecutionEngineInterface $engine = null,
        private readonly SecurityLimits $limits = new SecurityLimits(),
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
        $startedAt = Instant::of($this->clock->nowSeconds());
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

    private function completeSuccess(ExecutionInterface $execution, ExecutionScope $scope): void
    {
        $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());

        $completed = $this->coordinator->transition($execution, ExecutionSuccess::create(), Instant::of($this->clock->nowSeconds()));

        $this->publishTerminal($completed);
    }

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

        $completed = $this->coordinator->transition($execution, ExecutionFailure::of(ExecutionStatus::Failed, $description), Instant::of($this->clock->nowSeconds()));

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
    private function detachSafely(\ItPack\RunIt\Contract\Port\ContextTokenInterface $token): void
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

    private function safePublish(ExecutionArtifactInterface $artifact): void
    {
        $this->safePublisher->publish($artifact);
    }
}
