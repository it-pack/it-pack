<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SequencedInterface;
use ItPack\RunIt\Contract\Domain\TimestampedInterface;

/**
 * Immutable artifact representing a scheduler lifecycle event.
 *
 * Published by {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * at batch/flush boundaries for task spawn, suspension, resumption, and terminal
 * transitions. Each artifact carries the task id, execution id, event kind
 * (aligned to {@see ArtifactVocabulary}), the task status at the time of the
 * event, and a monotonic {@see DurationInterface} measurement along with a
 * sequence number.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 */
final readonly class SchedulerLifecycleArtifact implements
    ExecutionArtifactInterface,
    ExecutionOwnedInterface,
    SequencedInterface,
    TimestampedInterface,
    SchedulerLifecycleArtifactInterface
{
    public function __construct(
        public TaskIdInterface $taskId,
        public ExecutionIdInterface $executionId,
        public string $kind,
        public TaskStatus $status,
        public ?DurationInterface $duration = null,
        public ?ArtifactSequenceInterface $sequence = null,
        public ?InstantInterface $timestamp = null,
    ) {
    }

    public function taskId(): TaskIdInterface
    {
        return $this->taskId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function duration(): ?DurationInterface
    {
        return $this->duration;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function sequence(): int
    {
        return $this->sequence?->value() ?? 0;
    }

    public function count(): int
    {
        return $this->sequence();
    }

    public function timestamp(): int
    {
        return $this->timestamp?->epochSeconds() ?? 0;
    }

    public function normalize(): array
    {
        return [
            'kind' => $this->kind,
            'taskId' => $this->taskId->value,
            'executionId' => $this->executionId->toString(),
            'status' => $this->status->value,
            'duration' => $this->duration?->nanoseconds(),
            'sequence' => $this->sequence(),
            'timestamp' => $this->timestamp(),
        ];
    }
}
