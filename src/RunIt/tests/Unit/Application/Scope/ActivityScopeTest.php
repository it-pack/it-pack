<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scope;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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
 * @group scope
 */
final class ActivityScopeTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    public function testActivityScopeDelegatesExecutionLevelAccessAndExposesActivity(): void
    {
        $seen = [];

        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) use (&$seen) {
                return $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('load'),
                        ActivityKind::fromString('io')
                    ),
                    function ($activityScope) use (&$seen, $scope): null {
                        $seen['activityName'] = $activityScope->activity()->definition()->name()->toString();
                        $seen['parentScope'] = $activityScope->parentScope();
                        $seen['sameExecution'] = $activityScope->execution() === $scope->execution();
                        $seen['sameContext'] = $activityScope->context() === $scope->context();
                        $seen['sameMeasurements'] = $activityScope->measurements() === $scope->measurements();

                        return null;
                    },
                );
            },
        );

        self::assertSame('load', $seen['activityName']);
        self::assertInstanceOf(ExecutionScopeInterface::class, $seen['parentScope']);
        self::assertTrue($seen['sameExecution']);
        self::assertTrue($seen['sameContext']);
        self::assertTrue($seen['sameMeasurements']);
    }

    public function testRecordingsInsideActivityBindToActivity(): void
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
                        $activityScope->measurements()->counter('inside', 7);

                        return null;
                    },
                );

                return null;
            },
        );

        /** @var list<MeasurementInterface> $measurements */
        $measurements = array_values(array_filter(
            $this->kit->artifacts(),
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof MeasurementInterface,
        ));

        self::assertCount(1, $measurements);
        self::assertNotNull(
            $measurements[0]->activityId(),
            'Recordings through the activity scope must bind to the activity.'
        );
    }
}
