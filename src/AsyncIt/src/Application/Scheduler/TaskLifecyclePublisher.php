<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Application\Artifact\ArtifactVocabulary;
use ItPack\AsyncIt\Application\Artifact\SchedulerFailureArtifact;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/**
 * Publishes scheduler lifecycle artifacts into the RunIt artifact stream.
 *
 * Extracted from {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * during the Phase 4 SRP split. Encapsulates artifact construction and
 * publication for task spawn, resumption, completion, and failure.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final readonly class TaskLifecyclePublisher implements TaskLifecyclePublisherInterface
{
    public function __construct(
        private ArtifactPublisherInterface $publisher,
        private SchedulerLifecycleArtifactFactory $lifecycleFactory,
        private MonotonicClockInterface $clock,
    ) {
    }

    public function publishSpawned(TaskInterface $task): void
    {
        $this->publisher->publish(
            $this->lifecycleFactory->create(
                $task->id(),
                ArtifactVocabulary::KIND_TASK_SPAWNED,
                TaskStatus::Created,
            ),
        );
    }

    public function publishResumed(TaskInterface $task, ?int $startTimeNanos = null): void
    {
        $duration = $this->durationFrom($startTimeNanos);

        $this->publisher->publish(
            $this->lifecycleFactory->create(
                $task->id(),
                ArtifactVocabulary::KIND_TASK_RESUMED,
                TaskStatus::Running,
                $duration,
                Instant::now(),
            ),
        );
    }

    public function publishCompleted(
        TaskInterface $task,
        TaskStatus $status,
        ?int $startTimeNanos = null,
        ?Throwable $throwable = null,
    ): void {
        $duration = $this->durationFrom($startTimeNanos);

        $this->publisher->publish(
            $this->lifecycleFactory->create(
                $task->id(),
                ArtifactVocabulary::KIND_TASK_COMPLETED,
                $status,
                $duration,
                Instant::now(),
            ),
        );

        if ($throwable instanceof Throwable) {
            $this->publishFailure($task, $throwable);
        }
    }

    public function publishFailure(TaskInterface $task, Throwable $throwable): void
    {
        $this->publisher->publish(new SchedulerFailureArtifact(
            sprintf('task failure (%s): message redacted', $throwable::class)
        ));
    }

    private function durationFrom(?int $startTimeNanos): ?DurationInterface
    {
        return $startTimeNanos === null
            ? null
            : Duration::ofNanoseconds($this->clock->monotonicNanoseconds() - $startTimeNanos);
    }
}
