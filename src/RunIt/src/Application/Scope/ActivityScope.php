<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Scope;

use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * The ambient view of a running activity, delegating execution-level access to its parent.
 *
 * Recordings made through the activity scope bind to the activity automatically because the
 * parent scope's active activity identity has been set; everything else (execution, context,
 * and the other recorders) is delegated to the parent execution scope.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final readonly class ActivityScope implements ActivityScopeInterface
{
    public function __construct(
        private ExecutionScopeInterface $parentScope,
        private ActivityInterface       $activity,
    ) {
    }

    public function activity(): ActivityInterface
    {
        return $this->activity;
    }

    public function parentScope(): ExecutionScopeInterface
    {
        return $this->parentScope;
    }

    public function execution(): ExecutionInterface
    {
        return $this->parentScope->execution();
    }

    public function context(): ExecutionContextInterface
    {
        return $this->parentScope->context();
    }

    public function activities(): ActivityRunnerInterface
    {
        return $this->parentScope->activities();
    }

    public function measurements(): MeasurementRecorderInterface
    {
        return $this->parentScope->measurements();
    }

    public function observations(): ObservationRecorderInterface
    {
        return $this->parentScope->observations();
    }

    public function occurrences(): OccurrenceRecorderInterface
    {
        return $this->parentScope->occurrences();
    }

    public function snapshots(): SnapshotRecorderInterface
    {
        return $this->parentScope->snapshots();
    }
}
