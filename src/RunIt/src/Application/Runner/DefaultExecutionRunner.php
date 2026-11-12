<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\BoundedArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
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
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use RuntimeException;
use Throwable;

/**
 * Default lifecycle-safe execution runner.
 *
 * The runner validates and resolves the execution definition (applying the configured nested
 * policy), allocates identity and sequence, attaches an ambient scope, invokes the operation,
 * records the outcome, and always restores the prior context. On success it returns the
 * operation's result unchanged; on failure it records the failure and rethrows the exact
 * original throwable. Publication failures are caught and forwarded to the configured health
 * callback so they can never replace the business result or exception. Completion-path faults
 * (completion policies, failure recording, lifecycle transitions) are staged the same way:
 * each stage degrades independently, reports through the health callback, and can never mask
 * the business outcome or replace the original throwable.
 *
 * The runner's built-in publication stack is only Bounded→FailSafe(fail-open): data-protection
 * redaction and sampling reach the pipeline because {@see \ItPack\RunIt\SDK\Builder\RunItBuilder}
 * composes them around the configured publisher via
 * {@see \ItPack\RunIt\Application\Factory\PublisherStackFactory}. A directly constructed runner
 * publishes unredacted artifacts; prefer the builder unless this is explicitly intended.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultExecutionRunner implements ExecutionRunnerInterface
{
    use InstantClockBehavior;

    /**
     * Diagnostic code recorded on the failure observation of a failed execution.
     */
    private const string FAILURE_DIAGNOSTIC_CODE = 'execution.failure';

    /**
     * Stable message prefix for the failure observation; adapters map the rest.
     */
    private const string FAILURE_MESSAGE_PREFIX = 'Execution failed: ';

    /**
     * Placeholder for identities that cannot be resolved while rejecting a nested run.
     */
    private const string UNKNOWN_IDENTITY = 'unknown';

    /**
     * @param Closure(Throwable): void|null $onPublicationFailure receives publication errors so they can be reported
     *      without replacing the business result
     */
    private FailSafeArtifactPublisher $safePublisher;

    private ExecutionEngineInterface  $engine;

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
        ?ExecutionEngineInterface                     $engine = null,
        private SecurityLimits                         $limits = new SecurityLimits(),
        private ProvenanceAuthority                   $authority = new ProvenanceAuthority(),
    ) {
        $health = $this->onPublicationFailure instanceof Closure
            ? new CallbackHealthReporter($this->onPublicationFailure)
            : null;
        $bounded = new BoundedArtifactPublisher(
            $this->limits,
            $this->publisher,
            function (string $reason, ExecutionArtifactInterface $artifact): void {
                // Bound-exceeded drops become observable notices through the
                // same guarded health path as every other internal fault
                // (Phase 0, T0.3).
                $this->reportFailure(BoundDropException::forReason($reason), $artifact);
            },
        );
        $this->safePublisher = new FailSafeArtifactPublisher($bounded, new FailOpenPolicy(), $health);
        $this->engine = $engine ?? new SynchronousExecutionEngine();
    }

    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        $current = $this->currentExecution->get();
        $mode = $this->nestedPolicy->resolve($current, $definition);

        return match ($mode) {
            NestedExecutionMode::RejectNested => throw RejectedNestedExecutionException::forExecution(
                $current?->execution()->id()->toString() ?? self::UNKNOWN_IDENTITY,
            ),
            NestedExecutionMode::ActivityOnly => $this->runAsActivity($current, $definition, $operation),
            default => $this->runExecution($mode, $current, $definition, $operation),
        };
    }

    private function runAsActivity(
        ?ExecutionScopeInterface $current,
        ExecutionDefinitionInterface $definition,
        callable $operation
    ): mixed {
        if (!$current instanceof ExecutionScopeInterface) {
            return $this->runExecution(
                NestedExecutionMode::ChildExecution,
                current: null,
                definition: $definition,
                operation: $operation,
            );
        }

        $activityDefinition = ActivityDefinition::create(
            ActivityName::fromString($definition->name()->toString()),
            ActivityKind::fromString($definition->kind()->toString()),
        );

        return $current->activities()->run($activityDefinition, $operation);
    }

    private function runExecution(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
        ExecutionDefinitionInterface $definition,
        callable $operation,
    ): mixed {
        // Setup faults (definition resolution, identity allocation, start, scope
        // assembly, attach) are genuine configuration errors and still surface to
        // the caller — but they are always reported first, so no execution can
        // fail to materialize silently (Phase 0, T0.2).
        $id = null;
        $exportId = null;

        try {
            $parentId =
                ($mode === NestedExecutionMode::ChildExecution && $current instanceof ExecutionScopeInterface)
                    ? $current->execution()->id()
                    : null;
            $linkedIds =
                ($mode === NestedExecutionMode::DetachedExecution && $current instanceof ExecutionScopeInterface)
                    ? [$current->execution()->id()]
                    : [];

            $effectiveDefinition = ExecutionDefinition::create(
                $definition->name(),
                $definition->kind(),
                $parentId,
                $linkedIds,
                $definition->context(),
            );

            $rawId = $this->executionIdFactory->build();
            $id = ExecutionId::fromString($rawId->toString());

            // Reject a repeated execution ID before any provenance record is
            // issued, so two live executions can never share an ID and releasing
            // one cannot drop the other's export bindings.
            if ($this->authority->hasLiveRecords($id->toString())) {
                throw new RuntimeException(
                    'Execution ID ' . $id->toString()
                    . ' already has live provenance records; duplicate execution ID rejected.'
                );
            }

            $startedAt = $this->now();
            $sequence = $this->sequenceAllocator->allocate($id);

            // Issue an SDK-controlled export ID bound to this execution. Business
            // IDs (from the caller's generator) are preserved but never trusted as
            // provenance; only the export ID verified against this authority counts.
            $exportId = $this->authority->issueExportId(
                'execution',
                'execution',
                $id->toString(),
                $rawId->toString(),
            );

            // Register verified parent/link edges. A parent supplied in an activity
            // definition is an unverified claim; only an edge recorded by this
            // authority establishes trust.
            $parentExportId = null;
            $linkedExportIds = [];

            if ($mode === NestedExecutionMode::ChildExecution && $current instanceof ExecutionScopeInterface) {
                $parentBusinessId = $current->execution()->id()->toString();
                $parentExportId = $this->findExportIdForBusinessId($parentBusinessId, $id->toString());
                if ($parentExportId !== null) {
                    $this->authority->registerRelationship($parentExportId, $exportId, 'parent');
                }
            }

            $linkedExportIds = [];

            if ($mode === NestedExecutionMode::DetachedExecution && $current instanceof ExecutionScopeInterface) {
                $linkedBusinessId = $current->execution()->id()->toString();
                $linkedExportId = $this->findExportIdForBusinessId($linkedBusinessId, $id->toString());
                if ($linkedExportId !== null) {
                    $this->authority->registerRelationship($linkedExportId, $exportId, 'link');
                }

                $linkedExportIds[] = ExecutionId::fromString($linkedExportId ?? $linkedBusinessId);
            }

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
                null,
                $this->authority,
            );

            $token = $this->contextStorage->attach($scope);
        } catch (Throwable $throwable) {
            // Release the export record on setup failure so it cannot be reused
            // by a later execution through exportIdForBusinessIdExcluding().
            if (isset($exportId) && $id instanceof ExecutionId) {
                $this->authority->releaseExecution($id->toString());
            }

            $this->reportFailure($throwable);

            throw $throwable;
        }

        // The startup publish is protected separately: an unexpected fault there
        // is reported and the business operation still runs (Phase 0, T0.1).
        try {
            $this->safePublish($execution);
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }

        try {
            $result = $this->engine->run($scope, $operation);

            $this->completeSuccess($execution, $scope);

            return $result;
        } catch (Throwable $throwable) {
            $this->completeFailure($execution, $scope, $throwable);

            throw $throwable;
        } finally {
            $this->detachSafely($token);
            $this->authority->releaseExecution($id->toString());
        }
    }

    private function completeSuccess(ExecutionInterface $execution, ExecutionScope $scope): void
    {
        try {
            $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }

        try {
            $completed = $this->coordinator->transition($execution, ExecutionSuccess::create(), $this->now());
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);

            return;
        }

        $this->publishTerminal($completed);
    }

    private function completeFailure(ExecutionInterface $execution, ExecutionScope $scope, Throwable $throwable): void
    {
        try {
            $this->completionPolicy->resolveOpenActivities($scope, $scope->openActivities());
        } catch (Throwable $failure) {
            $this->reportFailure($failure);
        }

        $description = null;
        try {
            $description = $this->throwableMapper->describe($throwable);
            $scope->observations()->record(
                self::FAILURE_MESSAGE_PREFIX . $description->message(),
                ObservationSeverity::Error,
                null,
                $throwable,
                self::FAILURE_DIAGNOSTIC_CODE,
            );
        } catch (Throwable $failure) {
            $this->reportFailure($failure);
        }

        try {
            $completed = $this->coordinator->transition(
                $execution,
                ExecutionFailure::of(ExecutionStatus::Failed, $description ?? $this->fallbackDescription($throwable)),
                $this->now(),
            );
        } catch (Throwable $failure) {
            $this->reportFailure($failure);

            return;
        }

        $this->publishTerminal($completed);
    }

    /**
     * Builds a minimal description directly from the original throwable when the configured
     * mapper itself fails, so the execution can still be completed as failed.
     */
    private function fallbackDescription(Throwable $throwable): ThrowableDescription
    {
        return ThrowableDescription::create(
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getCode(),
            previous: null,
            trace: null,
        );
    }

    /**
     * Publishes the completed execution and its terminal snapshot, isolating any
     * post-completion failure (serialization, hashing, or publication) so it can
     * never replace the original business result or throwable.
     */
    private function publishTerminal(ExecutionInterface $completed): void
    {
        try {
            $this->safePublish($completed);
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);

            return;
        }

        try {
            $snapshot = $this->snapshotFactory->terminal($completed);
            $this->safePublish($snapshot);
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
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
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }
    }

    /**
     * Finds the export ID bound to a business ID for the given execution context.
     *
     * Returns null when the business ID has no verified binding, which means the
     * parent/linked relationship is unverified and must not be trusted.
     */
    private function findExportIdForBusinessId(string $businessId, string $currentExecutionId): ?string
    {
        return $this->authority->exportIdForBusinessIdExcluding($businessId, $currentExecutionId);
    }

    private function reportFailure(Throwable $failure, ?ExecutionArtifactInterface $artifact = null): void
    {
        if (!$this->onPublicationFailure instanceof Closure) {
            return;
        }

        try {
            ($this->onPublicationFailure)($failure);
        } catch (Throwable $throwable) {
            // A throwing health callback must never replace the business
            // outcome: both faults land in the last-resort fallback sink.
            $artifactId = null;

            if ($artifact instanceof ExecutionArtifactInterface) {
                $id = $artifact->normalize()[ArtifactField::ID] ?? null;
                $artifactId = is_string($id) ? $id : null;
            }

            new FallbackErrorLogReporter()->report($failure, $throwable, $artifactId);
        }
    }

    /**
     * @throws Throwable
     */
    private function safePublish(ExecutionArtifactInterface $artifact): void
    {
        $this->safePublisher->publish($artifact);
    }
}
