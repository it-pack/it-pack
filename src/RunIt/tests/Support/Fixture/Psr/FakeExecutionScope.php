<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * Test double for {@see ExecutionScopeInterface} with no-op recorders and a
 * configurable observations recorder for assertions.
 */
final class FakeExecutionScope implements ExecutionScopeInterface
{
    public function __construct(
        private readonly ExecutionInterface $execution,
        private readonly ExecutionContextInterface $context,
        private readonly ObservationRecorderInterface $observations,
    ) {
    }

    public function execution(): ExecutionInterface
    {
        return $this->execution;
    }

    public function context(): ExecutionContextInterface
    {
        return $this->context;
    }

    public function activities(): ActivityRunnerInterface
    {
        return new NoopActivityRunner($this);
    }

    public function measurements(): MeasurementRecorderInterface
    {
        return new NoopMeasurementRecorder($this->execution->executionId());
    }

    public function observations(): ObservationRecorderInterface
    {
        return $this->observations;
    }

    public function occurrences(): OccurrenceRecorderInterface
    {
        return new NoopOccurrenceRecorder($this->execution->executionId());
    }

    public function snapshots(): SnapshotRecorderInterface
    {
        return new NoopSnapshotRecorder($this->execution->executionId());
    }
}
