<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group recorder
 */
final class DefaultObservationRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<ObservationInterface>
     */
    private function observations(): array
    {
        $result = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof ObservationInterface) {
                $result[] = $artifact;
            }
        }

        return $result;
    }

    public function testObservationWithoutActivityBindsExecutionOnly(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->observations()->record('plain message', ObservationSeverity::Warning);

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertSame('plain message', $observations[0]->message());
        self::assertSame(ObservationSeverity::Warning, $observations[0]->severity());
        self::assertNull($observations[0]->activityId());
    }

    public function testObservationInsideActivityBindsActivity(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('load'),
                        ActivityKind::fromString('io')
                    ),
                    function ($activityScope): null {
                        $activityScope->observations()->record('in activity', ObservationSeverity::Info);

                        return null;
                    },
                );

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertNotNull($observations[0]->activityId(), 'Observations inside an activity must bind the activity.');
    }

    public function testThrowableIsNormalizedThroughMapper(): void
    {
        $error = new RuntimeException('boom');

        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) use ($error): null {
                $scope->observations()->record('failed step', ObservationSeverity::Error, null, $error, 'step.failed');

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertSame('step.failed', $observations[0]->diagnosticCode());
        self::assertNotNull($observations[0]->throwableDescription());
        self::assertSame('boom', $observations[0]->throwableDescription()->message());
    }
}
