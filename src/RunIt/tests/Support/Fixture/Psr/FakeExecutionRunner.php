<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Instant;

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

        $execution = Execution::start(
            ExecutionId::fromString($key),
            $definition,
            Instant::now(),
        );

        $recorder = new RecordingObservationRecorder();
        $this->lastObservations = $recorder;

        $scope = new FakeExecutionScope(
            $execution,
            ExecutionContext::empty(),
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
