<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group recorder
 */
final class DefaultOccurrenceRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<OccurrenceInterface>
     */
    private function occurrences(): array
    {
        $result = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof OccurrenceInterface) {
                $result[] = $artifact;
            }
        }

        return $result;
    }

    public function testOccurrenceCarriesTypeSourceAndData(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
                $scope->occurrences()->record(
                    OccurrenceType::fromString('itpack.thing.done.v1'),
                    'app',
                    ['ok' => true],
                );

                return null;
            },
        );

        $occurrences = $this->occurrences();
        self::assertCount(1, $occurrences);
        self::assertSame('itpack.thing.done.v1', $occurrences[0]->type()->toString());
        self::assertSame('app', $occurrences[0]->source());
        self::assertSame(['ok' => true], $occurrences[0]->data());
        self::assertNull($occurrences[0]->activityId());
    }

    public function testExtensionAttributesArePreserved(): void
    {
        $extensions = AttributeCollection::fromArray(['trace_id' => 'abc123']);

        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) use ($extensions) {
                $scope->occurrences()->record(
                    OccurrenceType::fromString('itpack.thing.done.v1'),
                    'app',
                    null,
                    null,
                    $extensions,
                );

                return null;
            },
        );

        $occurrences = $this->occurrences();
        self::assertCount(1, $occurrences);
        self::assertArrayHasKey('trace_id', $occurrences[0]->extensionAttributes());
        self::assertSame('abc123', $occurrences[0]->extensionAttributes()['trace_id']);
    }

    public function testOccurrenceInsideActivityBindsActivity(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('load'),
                        ActivityKind::fromString('io')
                    ),
                    function ($activityScope) {
                        $activityScope->occurrences()->record(
                            OccurrenceType::fromString('itpack.thing.done.v1'),
                            'app',
                        );

                        return null;
                    },
                );

                return null;
            },
        );

        $occurrences = $this->occurrences();
        self::assertCount(1, $occurrences);
        self::assertNotNull($occurrences[0]->activityId(), 'Occurrences inside an activity must bind the activity.');
    }
}
