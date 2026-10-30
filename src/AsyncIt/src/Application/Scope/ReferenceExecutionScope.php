<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scope;

use BadMethodCallException;
use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * Lightweight RunIt execution scope used by the reference runtime.
 *
 * The AsyncIt reference scheduler only needs a scope object whose *identity*
 * the {@see \ItPack\RunIt\Contract\Port\ContextStorageInterface} can track per
 * resume; it never invokes the scope's recorders. Real per-fiber scopes are
 * supplied by RunIt in Phase 3. Accessors therefore throw — they are not part
 * of the reference scheduling contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final readonly class ReferenceExecutionScope implements ExecutionScopeInterface
{
    public function __construct(public string $discriminant)
    {
    }

    public function execution(): ExecutionInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function context(): ExecutionContextInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function activities(): ActivityRunnerInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function measurements(): MeasurementRecorderInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function observations(): ObservationRecorderInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function occurrences(): OccurrenceRecorderInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function snapshots(): SnapshotRecorderInterface
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }
}
