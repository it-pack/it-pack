<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Scope\ActivityScope;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Shared\Instant;
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
final class DefaultActivityRunner implements ActivityRunnerInterface
{
    public function __construct(
        private readonly ExecutionScope $scope,
        private readonly ExecutionIdInterface $executionId,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactPublisherInterface $publisher,
        private readonly ThrowableMapperInterface $throwableMapper,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ActivityIdInterface> */
        private readonly StringIdentifierFactoryInterface $activityIdFactory,
    ) {
    }

    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed
    {
        $activityId = $this->activityIdFactory->build();
        $startedAt = Instant::of($this->clock->nowSeconds());
        $sequence = $this->sequenceAllocator->allocate($this->executionId);
        $activity = Activity::start($activityId, $this->executionId, $definition, $startedAt, $sequence);

        $this->scope->trackOpenActivity($activity);
        $previousActivityId = $this->scope->currentActivityId();
        $this->scope->setCurrentActivityId($activityId);

        $this->publisher->publish($activity);

        try {
            $result = $operation(new ActivityScope($this->scope, $activity));

            $completed = $this->complete($activity, ActivityStatus::Succeeded, null);
            $this->publisher->publish($completed);

            return $result;
        } catch (Throwable $throwable) {
            $description = $this->throwableMapper->describe($throwable);
            $completed = $this->complete($activity, ActivityStatus::Failed, $description);
            $this->publisher->publish($completed);

            throw $throwable;
        } finally {
            $this->scope->clearOpenActivity($activityId);
            $this->scope->setCurrentActivityId($previousActivityId);
        }
    }

    private function complete(ActivityInterface $activity, ActivityStatusInterface $status, ?ThrowableDescriptionInterface $description): ActivityInterface
    {
        return $activity->complete($status, $description, Instant::of($this->clock->nowSeconds()));
    }
}
