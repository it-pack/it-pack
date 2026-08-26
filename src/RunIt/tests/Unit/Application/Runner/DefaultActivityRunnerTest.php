<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group activity
 */
final class DefaultActivityRunnerTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    private function executionDefinition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task'));
    }

    private function activityDefinition(): \ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface
    {
        return \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io')
        );
    }

    /**
     * @return list<ActivityInterface>
     */
    private function terminalActivities(): array
    {
        $result = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof ActivityInterface && $artifact->status()->isTerminal()) {
                $result[] = $artifact;
            }
        }

        return $result;
    }

    public function testSuccessfulActivityReturnsResultAndPublishesCompletedActivity(): void
    {
        $result = $this->kit->runner->run(
            $this->executionDefinition(),
            function ($scope) {
                return $scope->activities()->run(
                    $this->activityDefinition(),
                    static fn () => 'inner',
                );
            },
        );

        self::assertSame('inner', $result);

        $activities = array_values(array_filter(
            $this->kit->artifacts(),
            static fn ($artifact): bool => $artifact instanceof ActivityInterface,
        ));
        // The activity runner publishes both the started and the completed activity.
        self::assertCount(2, $activities);
        self::assertSame('load', $activities[1]->definition()->name()->toString());
        self::assertSame(ActivityStatus::Succeeded, $activities[1]->status());
    }

    public function testActivityFailureIsRecordedAndRethrowsOriginalThrowable(): void
    {
        $thrown = new RuntimeException('activity broke');
        $caught = null;

        try {
            $this->kit->runner->run(
                $this->executionDefinition(),
                function ($scope) use ($thrown) {
                    $scope->activities()->run(
                        $this->activityDefinition(),
                        static function () use ($thrown): void {
                            throw $thrown;
                        },
                    );
                },
            );
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($thrown, $caught);

        $terminal = $this->terminalActivities();
        self::assertCount(1, $terminal);
        self::assertSame(ActivityStatus::Failed, $terminal[0]->status());
    }

    public function testNestedActivityBindsMeasurementsAndRestoresContext(): void
    {
        $this->kit->runner->run(
            $this->executionDefinition(),
            function ($scope) {
                $scope->measurements()->counter('before', 1);

                $scope->activities()->run(
                    $this->activityDefinition(),
                    function ($activityScope) {
                        $activityScope->measurements()->counter('inside', 7);

                        return 'inner';
                    },
                );

                $scope->measurements()->counter('after', 2);

                return null;
            },
        );

        $measurements = array_values(array_filter(
            $this->kit->artifacts(),
            static fn ($artifact): bool => $artifact instanceof MeasurementInterface,
        ));
        self::assertCount(3, $measurements);

        $inner = null;
        $outer = [];
        foreach ($measurements as $measurement) {
            if ($measurement->name()->toString() === 'inside') {
                $inner = $measurement;

                continue;
            }

            $outer[] = $measurement;
        }

        self::assertNotNull($inner);
        self::assertNotNull($inner->activityId(), 'Measurements inside an activity must be bound to the activity.');
        foreach ($outer as $measurement) {
            self::assertNull($measurement->activityId(), 'Measurements outside an activity must not be bound.');
        }
    }
}
