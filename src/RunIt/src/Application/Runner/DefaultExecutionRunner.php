<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Propagation\SafeArtifactPublisher;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
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
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\Instant;
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
    private readonly SafeArtifactPublisher $safePublisher;
    public function __construct(
        private readonly CurrentExecutionInterface $currentExecution,
        private readonly ContextStorageInterface $contextStorage,
        private readonly ArtifactPublisherInterface $publisher,
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
    ) {
        $this->safePublisher = new SafeArtifactPublisher($this->publisher, $this->onPublicationFailure);
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
            $this->throwableMapper,
            $this->observationIdFactory,
            $this->occurrenceIdFactory,
            $this->snapshotIdFactory,
            $this->activityIdFactory,
            $this->hashCalculator,
        );

        $token = $this->contextStorage->attach($scope);

        try {
            $this->safePublish($execution);

            $result = $operation($scope);

            $this->completeSuccess($execution, $scope);

            return $result;
        } catch (Throwable $throwable) {
            $this->completeFailure($execution, $scope, $throwable);

            throw $throwable;
        } finally {
            $this->contextStorage->detach($token);
        }
    }

    private function completeSuccess(ExecutionInterface $execution, ExecutionScope $scope): void
    {
        $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());

        $result = ExecutionSuccess::create();
        $completed = $this->coordinator->transition($execution, $result, Instant::of($this->clock->nowSeconds()));
        $snapshot = $this->snapshotFactory->terminal($completed);

        $this->safePublish($completed);
        $this->safePublish($snapshot);
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

        $result = ExecutionFailure::of(ExecutionStatus::Failed, $description);
        $completed = $this->coordinator->transition($execution, $result, Instant::of($this->clock->nowSeconds()));
        $snapshot = $this->snapshotFactory->terminal($completed);

        $this->safePublish($completed);
        $this->safePublish($snapshot);
    }

    private function safePublish(ExecutionArtifactInterface $artifact): void
    {
        try {
            $this->publisher->publish($artifact);
        } catch (Throwable $publicationError) {
            if ($this->onPublicationFailure instanceof Closure) {
                ($this->onPublicationFailure)($publicationError);
            }
        }
    }
}
