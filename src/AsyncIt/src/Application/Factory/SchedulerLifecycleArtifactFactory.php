<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Artifact\SchedulerLifecycleArtifact;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;

/**
 * Factory for constructing {@see SchedulerLifecycleArtifact} instances.
 *
 * Centralises construction so the scheduler never uses `new` outside a
 * Factory/Builder namespace (AsyncIt M1 architectural rule).
 *
 * @see https://w3id.org/itpack/asyncit#Task
 */
final readonly class SchedulerLifecycleArtifactFactory
{
    public function __construct(
        private SequenceAllocatorInterface $sequenceAllocator,
        private ExecutionIdInterface $executionId,
    ) {
    }

    public function create(
        TaskIdInterface $taskId,
        string $kind,
        TaskStatus $status,
        ?DurationInterface $duration = null,
        ?InstantInterface $timestamp = null,
    ): SchedulerLifecycleArtifact {
        return new SchedulerLifecycleArtifact(
            $taskId,
            $this->executionId,
            $kind,
            $status,
            $duration,
            $this->sequenceAllocator->allocate($this->executionId),
            $timestamp,
        );
    }
}
