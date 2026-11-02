<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Artifact\SchedulerLifecycleArtifactInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;

/**
 * Factory for constructing {@see SchedulerLifecycleArtifact} instances.
 *
 * Centralises construction so the scheduler never uses `new` outside a
 * Factory/Builder namespace.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @api
 */
interface SchedulerLifecycleArtifactFactoryInterface
{
    /**
     * Creates a lifecycle artifact for a task terminal transition.
     */
    public function create(
        TaskIdInterface $taskId,
        string $kind,
        TaskStatus $status,
        ?DurationInterface $duration = null,
        ?InstantInterface $timestamp = null,
    ): SchedulerLifecycleArtifactInterface;
}
