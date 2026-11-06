<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group recorder
 */
final class DefaultMeasurementRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<MeasurementInterface>
     */
    private function measurements(): array
    {
        $result = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof MeasurementInterface) {
                $result[] = $artifact;
            }
        }

        return $result;
    }

    public function testExecutionLevelMeasurementBindsExecutionAndPublishes(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->measurements()->counter('hits', 3);

                return null;
            },
        );

        $measurements = $this->measurements();
        self::assertCount(1, $measurements);
        self::assertSame('hits', $measurements[0]->name()->toString());
        self::assertSame(3, $measurements[0]->value());
        self::assertNull($measurements[0]->activityId(), 'Execution-level measurements must not bind an activity.');
    }

    public function testActivityLevelMeasurementBindsActivity(): void
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
                        $activityScope->measurements()->gauge('latency', 12.5);

                        return null;
                    },
                );

                return null;
            },
        );

        $measurements = $this->measurements();
        self::assertCount(1, $measurements);
        self::assertNotNull($measurements[0]->activityId(), 'Activity-level measurements must bind the activity.');
    }
}
