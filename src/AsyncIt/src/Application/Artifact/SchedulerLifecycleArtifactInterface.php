<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Immutable scheduler lifecycle artifact surface.
 *
 * Backs {@see SchedulerLifecycleArtifact} so lifecycle events remain mockable
 * and interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @api
 */
interface SchedulerLifecycleArtifactInterface
{
    public function taskId(): TaskIdInterface;

    public function executionId(): ExecutionIdInterface;

    public function kind(): string;

    public function status(): TaskStatus;

    public function duration(): ?DurationInterface;

    public function sequence(): int;

    public function timestamp(): int;

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array;
}
