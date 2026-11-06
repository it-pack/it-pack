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
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * Minimal activity runner that runs the operation inside an {@see ActivityScopeInterface}.
 */
final readonly class NoopActivityRunner implements ActivityRunnerInterface
{
    public function __construct(private ExecutionScopeInterface $parent)
    {
    }

    public function run(ActivityDefinitionInterface $definition, callable $operation): mixed
    {
        $activity = Activity::start(
            ActivityId::fromString('activity-' . spl_object_id($this)),
            $this->parent->execution()->executionId(),
            $definition,
            Instant::now(),
        );

        $scope = new readonly class ($this->parent, $activity) implements ActivityScopeInterface {
            public function __construct(
                private ExecutionScopeInterface $parent,
                private ActivityInterface $activity,
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

            public function execution(): ExecutionInterface
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
