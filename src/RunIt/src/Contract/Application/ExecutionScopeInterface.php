<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * The ambient, immutable view of a running execution handed to a consumer operation.
 *
 * A scope exposes the running execution record, its immutable context, and the
 * recorders and runners an operation uses to produce artifacts. The scope itself
 * is a runtime collaborator: its recorders bind newly created artifacts to the
 * execution (and any active activity) automatically.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionScopeInterface
{
    public function execution(): ExecutionInterface;

    public function context(): ExecutionContextInterface;

    public function activities(): ActivityRunnerInterface;

    public function measurements(): MeasurementRecorderInterface;

    public function observations(): ObservationRecorderInterface;

    public function occurrences(): OccurrenceRecorderInterface;

    public function snapshots(): SnapshotRecorderInterface;
}
