<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

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

/**
 * Test double for {@see ExecutionRunnerInterface} that runs the operation inside a
 * {@see FakeExecutionScope}, capturing the last definition and restoring nothing
 * (the scope is dropped when the call returns).
 */
final class FakeExecutionRunner implements ExecutionRunnerInterface
{
    private int $counter = 0;

    /**
     * @var array<string, ExecutionDefinitionInterface>
     */
    private array $definitions = [];

    private ?RecordingObservationRecorder $lastObservations = null;

    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        $key = 'fake-' . $this->counter;
        $this->counter++;
        $this->definitions[$key] = $definition;

        $execution = \ItPack\RunIt\Domain\Execution\Execution::start(
            \ItPack\RunIt\Domain\Execution\ExecutionId::fromString($key),
            $definition,
            \ItPack\RunIt\Domain\Shared\Instant::now(),
        );

        $recorder = new RecordingObservationRecorder();
        $this->lastObservations = $recorder;

        $scope = new FakeExecutionScope(
            $execution,
            \ItPack\RunIt\Domain\Context\ExecutionContext::empty(),
            $recorder,
        );

        return $operation($scope);
    }

    public function lastDefinition(): ?ExecutionDefinitionInterface
    {
        $last = end($this->definitions);

        return $last === false ? null : $last;
    }

    public function lastObservations(): ?RecordingObservationRecorder
    {
        return $this->lastObservations;
    }
}

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

final class NoopMeasurementRecorder implements MeasurementRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    private function measurement(): MeasurementInterface
    {
        return \ItPack\RunIt\Domain\Measurement\Measurement::counter(
            \ItPack\RunIt\Domain\Measurement\MeasurementName::fromString('noop'),
            $this->executionId,
            0,
        );
    }

    public function counter(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, int $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function gauge(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function duration(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function size(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, int $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function ratio(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }
}

final class NoopOccurrenceRecorder implements OccurrenceRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    public function record(
        \ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = 'application/json',
    ): OccurrenceInterface {
        return \ItPack\RunIt\Domain\Occurrence\Occurrence::create(
            \ItPack\RunIt\Domain\Occurrence\OccurrenceId::fromString('noop-occurrence'),
            $this->executionId,
            $type,
            $source,
            \ItPack\RunIt\Domain\Shared\Instant::now(),
            $data,
        );
    }
}

final class NoopSnapshotRecorder implements SnapshotRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    public function record(
        \ItPack\RunIt\Contract\Domain\SnapshotTypeInterface $type,
        mixed $payload,
        ?\ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface $classification = null,
        int $schemaVersion = 1,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface {
        return \ItPack\RunIt\Domain\Snapshot\Snapshot::create(
            \ItPack\RunIt\Domain\Snapshot\SnapshotId::fromString('noop-snapshot'),
            $this->executionId,
            $type,
            $schemaVersion,
            $payload,
            new \ItPack\RunIt\Domain\Shared\DeterministicHashCalculator(),
            null,
            null,
            $classification,
        );
    }
}
