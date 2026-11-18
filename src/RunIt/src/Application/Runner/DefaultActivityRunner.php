<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Scope\ActivityScope;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/**
 * Default activity runner.
 *
 * It creates an activity bound to the ambient execution, attaches an {@see ActivityScope},
 * invokes the operation, completes the activity (succeeded or failed), restores the previous
 * activity context, and always rethrows the original throwable. Recordings made inside the
 * operation bind to this activity because the parent scope's active activity identity is set
 * for the duration of the call.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final readonly class DefaultActivityRunner implements ActivityRunnerInterface
{
    use InstantClockBehavior;

    public function __construct(
        private ExecutionScope                   $scope,
        private ExecutionIdInterface             $executionId,
        private SequenceAllocatorInterface       $sequenceAllocator,
        private MonotonicClockInterface          $clock,
        private ArtifactPublisherInterface       $publisher,
        private ThrowableMapperInterface         $throwableMapper,
        /** @var StringIdentifierFactoryInterface<ActivityIdInterface> */
        private StringIdentifierFactoryInterface $activityIdFactory,
        private SecurityLimits                   $limits = new SecurityLimits(),
        private ProvenanceAuthority              $authority = new ProvenanceAuthority(),
        private ?ActivityContextStorageInterface $activityStorage = null,
        private ?Closure                         $onActivityContextFailure = null,
        private bool                             $runOnActivityContextAttachFailure = false,
    ) {
    }

    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed
    {
        $depth = count($this->scope->openActivities()) + 1;

        if ($depth > $this->limits->maxActivityDepth) {
            throw ActivityDepthExceededException::forDepth($depth, $this->limits->maxActivityDepth);
        }

        $activityId = $this->activityIdFactory->build();
        $startedAt = $this->currentInstant();
        $startedAtMonotonic = $this->clock->monotonicNanoseconds();
        $sequence = $this->sequenceAllocator->allocate($this->executionId);

        // Issue an SDK-controlled export ID for the activity, bound to the owning
        // execution. Register the execution→activity relationship edge so it can be
        // verified at publication; an activity definition's parent ID alone is not
        // trusted without a recorded edge.
        $exportId = $this->authority->bindArtifact(
            'activity',
            'activity',
            $this->executionId->toString(),
            $activityId->toString(),
            'execution-activity',
        );

        $activity = Activity::start($activityId, $this->executionId, $definition, $startedAt, $sequence);
        $this->authority->attest($activity, $exportId);
        $parentActivityId = $definition->parentActivityId()?->toString();
        if ($exportId !== null && $parentActivityId !== null) {
            $executionId = $this->authority->executionIdForExportId($exportId);
            $parentExportId = $executionId !== null
                ? $this->authority->exportIdForBusinessId($parentActivityId, $executionId, 'activity')
                : null;
            if ($parentExportId !== null) {
                $this->authority->bindActivityRelationship($exportId, $parentExportId, true);
            }
        }

        $this->scope->trackOpenActivity($activity);
        return $this->runActivity($activity, $activityId, $startedAtMonotonic, $operation);
    }

    private function currentInstant(): InstantInterface
    {
        return $this->now();
    }

    private function runActivity(
        ActivityInterface $activity,
        ActivityIdInterface $activityId,
        int $startedAtMonotonic,
        callable $operation,
    ): mixed {
        $previousActivityId = $this->scope->currentActivityId();
        $token = null;
        $activityScope = new ActivityScope($this->scope, $activity);
        try {
            try {
                $token = $this->attach($activityScope, $activityId);
            } catch (Throwable $failure) {
                if (!$this->runOnActivityContextAttachFailure) {
                    throw $failure;
                }

                return $this->scope->runWithoutActivityContext(
                    fn (): mixed => $this->runAttached($activity, $activityScope, $startedAtMonotonic, $operation),
                );
            }

            return $this->runAttached($activity, $activityScope, $startedAtMonotonic, $operation);
        } finally {
            try {
                if ($token instanceof ContextTokenInterface) {
                    $this->activityStorage?->detach($token);
                }
            } catch (Throwable) {
                $this->reportFailure('activity_context_detach_failed');
            } finally {
                $this->scope->clearOpenActivity($activityId);
                if (!$this->activityStorage instanceof ActivityContextStorageInterface) {
                    $this->scope->setCurrentActivityId($previousActivityId);
                }
            }
        }
    }

    private function runAttached(
        ActivityInterface $activity,
        ActivityScope $activityScope,
        int $startedAtMonotonic,
        callable $operation,
    ): mixed {
        try {
            $this->publisher->publish($activity);
        } catch (Throwable) {
            $this->reportFailure('activity_start_publication_failed');
        }

        $result = $this->invokeOperation($operation, $activityScope, $activity, $startedAtMonotonic);
        $this->publishSuccess($activity, $startedAtMonotonic);

        return $result;
    }

    private function invokeOperation(
        callable $operation,
        ActivityScope $scope,
        ActivityInterface $activity,
        int $startedAtMonotonic,
    ): mixed {
        try {
            return $operation($scope);
        } catch (Throwable $throwable) {
            $this->publishFailure($activity, $throwable, $startedAtMonotonic);
            throw $throwable;
        }
    }

    private function attach(ActivityScope $activityScope, ActivityIdInterface $activityId): ?ContextTokenInterface
    {
        if (!$this->activityStorage instanceof ActivityContextStorageInterface) {
            $this->scope->setCurrentActivityId($activityId);
            return null;
        }

        try {
            return $this->activityStorage->attach($activityScope);
        } catch (Throwable $throwable) {
            $this->reportFailure('activity_context_attach_failed');
            throw $throwable;
        }
    }

    private function publishFailure(ActivityInterface $activity, Throwable $throwable, int $startedAtMonotonic): void
    {
        try {
            $description = $this->throwableMapper->describe($throwable);
            $completed = $this->complete($activity, ActivityStatus::Failed, $description, $startedAtMonotonic);
            $this->authority->attestSuccessor($activity, $completed);
            $this->publisher->publish($completed);
        } catch (Throwable) {
            $this->reportFailure('activity_terminal_publication_failed');
        }
    }

    private function publishSuccess(ActivityInterface $activity, int $startedAtMonotonic): void
    {
        try {
            $completed = $this->complete($activity, ActivityStatus::Succeeded, null, $startedAtMonotonic);
            $this->authority->attestSuccessor($activity, $completed);
            $this->publisher->publish($completed);
        } catch (Throwable) {
            $this->reportFailure('activity_terminal_publication_failed');
        }
    }

    private function reportFailure(string $reason): void
    {
        // Guard against re-entry: a reporting callback that re-enters this runner
        // before the first report returns would recurse into the publication path.
        // Bound nested reports are suppressed; later independent reports proceed
        // once the guard is released in finally.
        /** @var array<int, bool> */
        static $reportingInstances = [];

        $id = spl_object_id($this);
        if (array_key_exists($id, $reportingInstances)) {
            return;
        }

        $reportingInstances[$id] = true;

        try {
            if ($this->onActivityContextFailure instanceof Closure) {
                try {
                    ($this->onActivityContextFailure)($reason);
                } catch (Throwable) {
                    $this->reportFallback($reason);
                }
            }

            if ($this->onActivityContextFailure instanceof Closure) {
                return;
            }

            // No health callback is configured: report through an independent,
            // payload-free path so publication failures are not silently discarded.
            $this->reportFallback($reason);
        } finally {
            unset($reportingInstances[$id]);
        }
    }

    private function reportFallback(string $reason): void
    {
        // Independent, payload-free fallback. It does not traverse the publication
        // stack, so it cannot recurse into this runner or replace the business
        // result or exception.
        @error_log(sprintf('run-it: activity context failure: %s', $reason));
    }

    private function complete(
        ActivityInterface $activity,
        ActivityStatusInterface $status,
        ?ThrowableDescriptionInterface $description,
        int $startedAtMonotonic,
    ): ActivityInterface {
        $duration = $this->clock->measure($startedAtMonotonic, $this->clock->monotonicNanoseconds());

        return $activity->complete($status, $description, $this->now(), $duration);
    }
}
