<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

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
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
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
    ) {
    }

    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed
    {
        $depth = count($this->scope->openActivities()) + 1;

        if ($depth > $this->limits->maxActivityDepth) {
            throw ActivityDepthExceededException::forDepth($depth, $this->limits->maxActivityDepth);
        }

        $activityId = $this->activityIdFactory->build();
        $startedAt = $this->now();
        $startedAtMonotonic = $this->clock->monotonicNanoseconds();
        $sequence = $this->sequenceAllocator->allocate($this->executionId);
        $activity = Activity::start($activityId, $this->executionId, $definition, $startedAt, $sequence);

        $this->scope->trackOpenActivity($activity);
        $previousActivityId = $this->scope->currentActivityId();
        $this->scope->setCurrentActivityId($activityId);

        $this->publisher->publish($activity);

        try {
            $result = $operation(new ActivityScope($this->scope, $activity));

            $completed = $this->complete(
                $activity,
                ActivityStatus::Succeeded,
                description: null,
                startedAtMonotonic: $startedAtMonotonic,
            );
            $this->publisher->publish($completed);

            return $result;
        } catch (Throwable $throwable) {
            $description = $this->throwableMapper->describe($throwable);
            $completed = $this->complete($activity, ActivityStatus::Failed, $description, $startedAtMonotonic);
            $this->publisher->publish($completed);

            throw $throwable;
        } finally {
            $this->scope->clearOpenActivity($activityId);
            $this->scope->setCurrentActivityId($previousActivityId);
        }
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
