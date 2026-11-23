<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use ArrayObject;
use Closure;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;
use ItPack\RunIt\Application\Propagation\StackActivityContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\BoundedArtifactPublisher;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
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
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
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
    public FailSafeArtifactPublisher $safePublisher;

    public ExecutionEngineInterface  $engine;

    /** @var ArrayObject<string, bool> */
    public ArrayObject $healthState;

    public function __construct(
        public CurrentExecutionInterface              $currentExecution,
        public ContextStorageInterface                $contextStorage,
        public ArtifactPublisherInterface             $publisher,
        public SerializerInterface                    $serializer,
        public ExecutionFactoryInterface              $executionFactory,
        public ExecutionLifecycleCoordinatorInterface $coordinator,
        public ExecutionSnapshotFactoryInterface      $snapshotFactory,
        public NestedExecutionPolicyInterface         $nestedPolicy,
        public ExecutionCompletionPolicyInterface     $completionPolicy,
        public MonotonicClockInterface                $clock,
        public SequenceAllocatorInterface             $sequenceAllocator,
        /** @var StringIdentifierFactoryInterface<ExecutionIdInterface> */
        public StringIdentifierFactoryInterface       $executionIdFactory,
        /** @var StringIdentifierFactoryInterface<ActivityIdInterface> */
        public StringIdentifierFactoryInterface       $activityIdFactory,
        /** @var StringIdentifierFactoryInterface<ObservationIdInterface> */
        public StringIdentifierFactoryInterface       $observationIdFactory,
        /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
        public StringIdentifierFactoryInterface       $occurrenceIdFactory,
        /** @var StringIdentifierFactoryInterface<SnapshotIdInterface> */
        public StringIdentifierFactoryInterface       $snapshotIdFactory,
        public HashCalculatorInterface                $hashCalculator,
        public ThrowableMapperInterface               $throwableMapper,
        public ?Closure                               $onPublicationFailure = null,
        public ?SnapshotStoreInterface                $snapshotStore = null,
        public ?Closure                               $onSnapshotStoreFailure = null,
        ?ExecutionEngineInterface                     $engine = null,
        public SecurityLimits                         $limits = new SecurityLimits(),
        public ProvenanceAuthority                   $authority = new ProvenanceAuthority(),
        public ?ActivityContextStorageInterface      $activityContextStorage = null,
        public string                                  $activityContextPolicy = 'fallback',
        public bool                                  $runOnActivityContextAttachFailure = false,
    ) {
        $this->healthState = new ArrayObject(['reporting' => false]);
        $health = new readonly class (fn (Throwable $failure, ExecutionArtifactInterface $artifact) =>
            $this->reportFailure($failure, $artifact)) implements InternalHealthReporterInterface {
                public function __construct(private Closure $reportCallback)
                {
                }

                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    ($this->reportCallback)($error, $artifact);
                }
            };
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

    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     * @throws InvalidValueExceptionInterface
     * @throws LifecycleExceptionInterface
     */
    private function setupExecutionScope(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
        ExecutionDefinitionInterface $definition,
    ): ExecutionScopeSetup {
        $rawId = $this->executionIdFactory->build();
        $id = ExecutionId::fromString($rawId->toString());

        $parentId = $this->resolveParentId($mode, $current);
        $linkedIds = $this->resolveLinkedIds($mode, $current);

        $effectiveDefinition = ExecutionDefinition::create(
            $definition->name(),
            $definition->kind(),
            $parentId,
            $linkedIds,
            $definition->context(),
        );

        $startedAt = $this->now();
        $sequence = $this->sequenceAllocator->allocate($id);

        $duplicateId = $this->authority->hasLiveRecords($id->toString());

        $exportId = $this->issueExportIdIfNotDuplicate($duplicateId, $id->toString(), $rawId->toString());

        $scopeAuthority = $this->resolveScopeAuthority($duplicateId);

        try {
            $created = $this->executionFactory->create($effectiveDefinition, $id, $startedAt, $sequence);
            $execution = $this->coordinator->start($created);
            $this->authority->attest($execution, $exportId);

            // Register verified parent/link edges. A parent supplied in an activity
            // definition is an unverified claim; only an edge recorded by this
            // authority establishes trust.
            $this->registerExecutionEdges($mode, $current, $exportId);

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
                $scopeAuthority,
                $this->contextStorage,
                $this->activityContextStorage ?? ($this->contextStorage instanceof StackContextStorage
                    ? new StackActivityContextStorage()
                    : null),
                $this->activityContextPolicy,
                static fn (string $reason) => $scopeAuthority->reportUnverified(new RuntimeException($reason)),
                $this->runOnActivityContextAttachFailure,
            );

            return new ExecutionScopeSetup($scope, $execution, $id, $exportId, $scopeAuthority);
        } catch (Throwable $throwable) {
            if ($exportId !== null) {
                try {
                    $this->authority->releaseExecution($id->toString());
                } catch (Throwable $cleanupFailure) {
                    $this->reportFailure($cleanupFailure);
                }
            }

            throw $throwable;
        }
    }

    private function resolveParentId(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
    ): ?ExecutionIdInterface {
        return ($mode === NestedExecutionMode::ChildExecution && $current instanceof ExecutionScopeInterface)
            ? $current->execution()->id()
            : null;
    }

    /**
     * @return list<ExecutionIdInterface>
     */
    private function resolveLinkedIds(NestedExecutionMode $mode, ?ExecutionScopeInterface $current): array
    {
        if ($mode === NestedExecutionMode::DetachedExecution && $current instanceof ExecutionScopeInterface) {
            return [$current->execution()->id()];
        }

        return [];
    }

    private function issueExportIdIfNotDuplicate(bool $duplicateId, string $id, string $rawId): ?string
    {
        $exportId = null;
        try {
            if (!$duplicateId) {
                $exportId = $this->authority->issueExportId(
                    'execution',
                    'execution',
                    $id,
                    $rawId,
                );
            }

            if ($duplicateId) {
                $this->authority->reportUnverified(new RuntimeException('Duplicate execution identity.'));
            }
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Provenance issuance rejected.'));
        }

        return $exportId;
    }

    private function resolveScopeAuthority(bool $duplicateId): ProvenanceAuthority
    {
        return $duplicateId ? new ProvenanceAuthority() : $this->authority;
    }

    private function registerExecutionEdges(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
        ?string $exportId,
    ): void {
        if ($mode === NestedExecutionMode::ChildExecution && $current instanceof ExecutionScopeInterface) {
            $parentExportId = $this->authority->exportIdForArtifact($current->execution());
            if ($parentExportId !== null && $exportId !== null) {
                $this->authority->registerRelationship($parentExportId, $exportId, 'parent');
            }
        }

        if ($mode === NestedExecutionMode::DetachedExecution && $current instanceof ExecutionScopeInterface) {
            $linkedExportId = $this->authority->exportIdForArtifact($current->execution());
            if ($linkedExportId !== null && $exportId !== null) {
                $this->authority->registerRelationship($linkedExportId, $exportId, 'link');
            }
        }
    }


    /**
     * @throws Throwable
     * @throws InvalidValueExceptionInterface
     * @throws LifecycleExceptionInterface
     */
    private function runExecution(
        NestedExecutionMode $mode,
        ?ExecutionScopeInterface $current,
        ExecutionDefinitionInterface $definition,
        callable $operation,
    ): mixed {
        try {
            $scopeSetup = $this->setupExecutionScope($mode, $current, $definition);
            $token = $this->contextStorage->attach($scopeSetup->scope());
        } catch (Throwable $throwable) {
            // Release the export record on setup failure so it cannot be reused.
            if (isset($scopeSetup) && $scopeSetup->exportId() !== null && $scopeSetup->id() instanceof ExecutionId) {
                $this->authority->releaseExecution($scopeSetup->id()->toString());
            }

            $this->reportFailure($throwable);

            throw $throwable;
        }

        // The startup publish is protected separately: an unexpected fault there
        // is reported and the business operation still runs (Phase 0, T0.1).
        try {
            $this->safePublish($scopeSetup->execution());
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }

        try {
            $result = $this->engine->run($scopeSetup->scope(), $operation);

            $this->completeSuccess($scopeSetup->execution(), $scopeSetup->scope());

            return $result;
        } catch (Throwable $throwable) {
            $this->completeFailure($scopeSetup->execution(), $scopeSetup->scope(), $throwable);

            throw $throwable;
        } finally {
            $this->detachSafely($token);
            if ($scopeSetup->exportId() !== null) {
                $this->authority->releaseExecution($scopeSetup->id()->toString());
            }
        }
    }

    private function completeSuccess(ExecutionInterface $execution, ExecutionScope $scope): void
    {
        $openActivities = $scope->openActivities();
        $failOnOpenActivities = $openActivities !== []
            && $this->completionPolicy instanceof FailExecutionOnOpenActivitiesPolicy;
        try {
            $this->completionPolicy->resolveOpenActivities($scope, $openActivities);
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }

        try {
            $outcome = $failOnOpenActivities
                ? ExecutionFailure::of(ExecutionStatus::Failed, ThrowableDescription::create(
                    'open_activity_policy',
                    'Execution failed because activities remained open at completion.',
                    0,
                    null,
                    null,
                ))
                : ExecutionSuccess::create();
            $completed = $this->coordinator->transition($execution, $outcome, $this->now());
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);

            return;
        }

        $this->authority->attestSuccessor($execution, $completed);
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

        $this->authority->attestSuccessor($execution, $completed);
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

        $this->publishTerminalSnapshot($completed);
    }

    private function publishTerminalSnapshot(ExecutionInterface $completed): void
    {
        try {
            $snapshot = $this->snapshotFactory->terminal($completed);
            $executionExportId = $this->authority->exportIdForArtifact($completed);
            $executionId = $completed->executionId()->toString();

            if (!$this->verifyTerminalSnapshotProvenance($snapshot, $executionExportId, $executionId)) {
                $this->authority->reportUnverified(
                    new RuntimeException('Terminal snapshot provenance verification failed.'),
                );

                return;
            }

            $snapshotExportId = $this->resolveSnapshotExportId($snapshot, $executionId);
            if ($snapshotExportId === null) {
                return;
            }

            if (!$this->verifySnapshotExportId(
                $snapshot,
                $snapshotExportId,
                $executionId,
                $executionExportId,
                $completed
            )) {
                $this->authority->reportUnverified(
                    new RuntimeException('Terminal snapshot provenance verification failed.'),
                );

                return;
            }

            $this->safePublish($snapshot);
        } catch (Throwable $throwable) {
            $this->reportFailure($throwable);
        }
    }

    private function verifyTerminalSnapshotProvenance(
        ExecutionSnapshotInterface $snapshot,
        ?string $executionExportId,
        string $executionId,
    ): bool {
        $snapshotId = $snapshot->id()->toString();
        $snapshotExecutionId = $snapshot->executionId()->toString();
        $fields = $snapshot->normalize();

        return $executionExportId !== null
            && $snapshotExecutionId === $executionId
            && ($fields[ArtifactField::EXECUTION_ID] ?? null) === $executionId
            && ($fields[ArtifactField::ID] ?? null) === $snapshotId
            && $this->authority->verifyExportId($executionExportId, 'execution', $executionId);
    }

    private function resolveSnapshotExportId(
        ExecutionSnapshotInterface $snapshot,
        string $executionId,
    ): ?string {
        $snapshotExportId = $this->authority->exportIdForArtifact($snapshot);
        if ($snapshotExportId === null) {
            $snapshotExportId = $this->authority->bindArtifact(
                'snapshot',
                'terminal-snapshot',
                $executionId,
                $snapshot->id()->toString(),
                'execution-terminal-snapshot',
            );
            if ($snapshotExportId === null || !$this->authority->attest($snapshot, $snapshotExportId)) {
                $this->authority->reportUnverified(
                    new RuntimeException('Terminal snapshot provenance verification failed.'),
                );

                return null;
            }
        }

        return $snapshotExportId;
    }

    private function verifySnapshotExportId(
        ExecutionSnapshotInterface $snapshot,
        string $snapshotExportId,
        string $executionId,
        ?string $executionExportId,
        ExecutionInterface $completed,
    ): bool {
        return $this->authority->verifyExportId($snapshotExportId, 'terminal-snapshot', $executionId)
            && $this->authority->exportIdForArtifact($completed) === $executionExportId
            && $this->authority->exportIdentityFields($snapshot) !== null;
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

    /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
    private function reportFailure(Throwable $failure, ?ExecutionArtifactInterface $artifact = null): void
    {
        if ($this->healthState['reporting'] ?? false) {
            $this->reportPublicationFailureToFallback();

            return;
        }

        $this->healthState['reporting'] = true;
        try {
            $this->reportFailureOnce($failure, $artifact);
        } finally {
            $this->healthState['reporting'] = false;
        }
    }

    private function reportFailureOnce(Throwable $failure, ?ExecutionArtifactInterface $artifact): void
    {
        if ($this->onPublicationFailure instanceof Closure) {
            try {
                ($this->onPublicationFailure)($failure);

                return;
            } catch (Throwable) {
                $this->reportPublicationFailureToFallback();

                return;
            }
        }

        // No callback configured: the replaceable SDK default reports to the
        // PHP error log so a broken publisher stays observable even when the
        // runner is constructed directly without a health handler. An explicit
        // opt-out is a no-op closure passed as the handler, which reaches this
        // branch as a Closure and reports nothing.
        $this->reportToDefaultErrorLog($failure, $artifact);
    }

    private function reportToDefaultErrorLog(Throwable $failure, ?ExecutionArtifactInterface $artifact): void
    {
        $artifact ??= $this->placeholderArtifact();

        try {
            new ErrorLogHealthReporter($this->throwableMapper)->report($failure, $artifact);
        } catch (Throwable) {
            $this->reportPublicationFailureToFallback();
        }
    }

    private function reportPublicationFailureToFallback(): void
    {
        try {
            new FallbackErrorLogReporter()->report(
                new RuntimeException(FallbackErrorLogReporter::FALLBACK_PRIMARY_MESSAGE),
                new RuntimeException(FallbackErrorLogReporter::FALLBACK_HANDLER_FAULT_MESSAGE),
            );
        } catch (Throwable) {
            // No further independent sink remains; preserve the business outcome.
        }
    }

    private function placeholderArtifact(): ExecutionArtifactInterface
    {
        return new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };
    }

    /**
     * @throws Throwable
     */
    private function safePublish(ExecutionArtifactInterface $artifact): void
    {
        $this->safePublisher->publish($artifact);
    }
}
