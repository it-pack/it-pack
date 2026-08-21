<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;

/**
 * Minimal activity runner that runs the operation inside an {@see ActivityScopeInterface}.
 */
final class NoopActivityRunner implements ActivityRunnerInterface
{
    public function __construct(private readonly ExecutionScopeInterface $parent)
    {
    }

    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed
    {
        $activity = \ItPack\RunIt\Domain\Activity\Activity::start(
            \ItPack\RunIt\Domain\Activity\ActivityId::fromString('activity-' . spl_object_id($this)),
            $this->parent->execution()->executionId(),
            $definition,
            \ItPack\RunIt\Domain\Shared\Instant::now(),
        );

        $scope = new class ($this->parent, $activity) implements ActivityScopeInterface {
            public function __construct(
                private readonly ExecutionScopeInterface $parent,
                private readonly ActivityInterface $activity,
            ) {
            }

            public function activity(): ActivityInterface
            {
                return $this->activity;
            }

            public function parentScope(): ExecutionScopeInterface
            {
                return $this->parent;
            }

            public function execution(): \ItPack\RunIt\Contract\Domain\ExecutionInterface
            {
                return $this->parent->execution();
            }

            public function context(): ExecutionContextInterface
            {
                return $this->parent->context();
            }

            public function activities(): ActivityRunnerInterface
            {
                return $this->parent->activities();
            }

            public function measurements(): MeasurementRecorderInterface
            {
                return $this->parent->measurements();
            }

            public function observations(): ObservationRecorderInterface
            {
                return $this->parent->observations();
            }

            public function occurrences(): OccurrenceRecorderInterface
            {
                return $this->parent->occurrences();
            }

            public function snapshots(): SnapshotRecorderInterface
            {
                return $this->parent->snapshots();
            }
        };

        return $operation($scope);
    }
}
