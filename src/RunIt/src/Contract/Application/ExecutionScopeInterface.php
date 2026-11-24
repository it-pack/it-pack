<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

interface ExecutionScopeInterface
{
    public function execution(): ExecutionInterface;

    public function context(): ExecutionContextInterface;

    public function activities(): ActivityRunnerInterface;

    public function measurements(): MeasurementRecorderInterface;

    public function observations(): ObservationRecorderInterface;

    public function occurrences(): OccurrenceRecorderInterface;

    public function snapshots(): SnapshotRecorderInterface;

    public function currentActivityId(): ?ActivityIdInterface;

    public function setCurrentActivityId(?ActivityIdInterface $currentActivityId): void;

    public function trackOpenActivity(ActivityInterface $activity): void;

    public function clearOpenActivity(ActivityIdInterface $activityId): void;

    /**
     * @return list<ActivityInterface>
     */
    public function openActivities(): array;

    public function runWithoutActivityContext(callable $operation): mixed;
}
