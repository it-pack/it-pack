<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

final class OverlappingActivityProvenanceTest extends Unit
{
    public function testSharedExecutionScopeMisattributesInterleavedActivityObservations(): void
    {
        $authority = new ProvenanceAuthority();
        $kit = new RunItTestKit(storage: new FiberContextStorage(), authority: $authority);
        $execution = ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('task'));
        $activity = ActivityDefinition::create(ActivityName::fromString('test'), ActivityKind::fromString('task'));
        $kit->runner->run($execution, static function (ExecutionScopeInterface $scope) use (
            $activity,
            $authority,
        ): void {
            $ids = [];
            $observed = [];
            $first = new Fiber(static function () use ($scope, $activity, $authority, &$ids, &$observed): void {
                $scope->activities()->run($activity, static function (ActivityScopeInterface $active) use (
                    $authority,
                    &$ids,
                    &$observed,
                ): void {
                    $ids['first'] = $active->activity()->activityId()?->toString();
                    Fiber::suspend();
                    $observation = $active->observations()->record('first');
                    $observed['first'] = [$observation->normalize(), $authority->exportIdentityFields($observation)];
                });
            });
            $second = new Fiber(static function () use ($scope, $activity, $authority, &$ids, &$observed): void {
                $scope->activities()->run($activity, static function (ActivityScopeInterface $active) use (
                    $authority,
                    &$ids,
                    &$observed,
                ): void {
                    $ids['second'] = $active->activity()->activityId()?->toString();
                    Fiber::suspend();
                    $observation = $active->observations()->record('second');
                    $observed['second'] = [$observation->normalize(), $authority->exportIdentityFields($observation)];
                });
            });
            $first->start();
            $second->start();
            $first->resume();
            $second->resume();

            self::assertNotSame($ids['first'], $ids['second']);
            if ($ids['second'] === null || $observed['first'][1] === null || $observed['second'][1] === null) {
                throw new RuntimeException('Expected activity and observation provenance.');
            }

            $executionId = $scope->execution()->id()->toString();
            $secondExport = $authority->exportIdForBusinessId($ids['second'], $executionId, 'activity');
            self::assertNotNull($secondExport);
            // Characterizes the defect: the first observation is bound to the second activity.
            self::assertSame($ids['second'], $observed['first'][0]['activity_id']);
            self::assertSame($secondExport, $observed['first'][1]['activity_id']);
            self::assertNull($observed['second'][0]['activity_id']);
            self::assertArrayNotHasKey('activity_id', $observed['second'][1]);
        });
    }
}
