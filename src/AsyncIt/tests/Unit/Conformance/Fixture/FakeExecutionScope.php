<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Fixture;

use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * Minimal execution scope used by verifier fixtures.
 *
 * The AsyncIt context-isolation verifier only compares scope *identity*; it
 * never invokes a scope's accessors. This stand-in satisfies the RunIt
 * {@see ExecutionScopeInterface} contract without dragging in the full
 * execution model.
 */
final class FakeExecutionScope implements ExecutionScopeInterface
{
    public function __construct()
    {
    }

    public function execution(): ExecutionInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function context(): ExecutionContextInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function activities(): ActivityRunnerInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function measurements(): MeasurementRecorderInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function observations(): ObservationRecorderInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function occurrences(): OccurrenceRecorderInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }

    public function snapshots(): SnapshotRecorderInterface
    {
        throw new \BadMethodCallException('FakeExecutionScope is an identity stand-in.');
    }
}
