<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberActivityContextStorage;
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
use Tests\Support\Fixture\RunItTestKit;

final class OverlappingActivityProvenanceTest extends Unit
{
    public function testOverlappingActivitiesRetainTheirOwnAttributionInBothCompletionOrders(): void
    {
        foreach ([['first', 'second'], ['second', 'first']] as $order) {
            $authority = new ProvenanceAuthority();
            $storage = new FiberContextStorage();
            $activityStorage = new FiberActivityContextStorage();
            $kit = new RunItTestKit(
                storage: $storage,
                authority: $authority,
                activityContextStorage: $activityStorage,
            );
            $execution = ExecutionDefinition::create(
                ExecutionName::fromString('test'),
                ExecutionKind::fromString('task'),
            );
            $activity = ActivityDefinition::create(ActivityName::fromString('test'), ActivityKind::fromString('task'));
            $kit->runner->run($execution, static function (ExecutionScopeInterface $scope) use (
                $activity,
                $activityStorage,
                $authority,
                $order,
                $storage,
            ): void {
                $ids = [];
                $observed = [];
                $fibers = [];
                foreach (['first', 'second'] as $name) {
                    $fibers[$name] = new Fiber(static function () use (
                        $scope,
                        $activity,
                        $activityStorage,
                        $authority,
                        $storage,
                        $name,
                        &$ids,
                        &$observed,
                    ): void {
                        self::assertNull($storage->current());
                        self::assertNull($activityStorage->current());
                        $token = $storage->attach($scope);
                        try {
                            $scope->activities()->run($activity, static function (ActivityScopeInterface $active) use (
                                $scope,
                                $authority,
                                $storage,
                                $name,
                                &$ids,
                                &$observed,
                            ): void {
                                $ids[$name] = $active->activity()->activityId()?->toString();
                                Fiber::suspend();
                                self::assertSame($scope, $storage->current());
                                foreach ([$scope, $active] as $recorderScope) {
                                    $observation = $recorderScope->observations()->record($name);
                                    $observed[$name][] = [
                                        $observation->normalize(),
                                        $authority->exportIdentityFields($observation),
                                    ];
                                }
                            });
                            self::assertSame($scope, $storage->current());
                            self::assertNull($activityStorage->current());
                            $after = $scope->observations()->record('after');
                            self::assertNull($after->normalize()['activity_id']);
                            $afterFields = $authority->exportIdentityFields($after);
                            self::assertNotNull($afterFields);
                            self::assertArrayNotHasKey('activity_id', $afterFields);
                        } finally {
                            $storage->detach($token);
                        }

                        self::assertNull($storage->current());
                    });
                }

                $fibers['first']->start();
                $fibers['second']->start();
                foreach ($order as $name) {
                    $fibers[$name]->resume();
                }

                self::assertNotNull($ids['first']);
                self::assertNotNull($ids['second']);
                self::assertNotSame($ids['first'], $ids['second']);
                foreach (['first', 'second'] as $name) {
                    $id = $ids[$name];
                    self::assertNotNull($id);
                    $export = $authority->exportIdForBusinessId($id, $scope->execution()->id()->toString(), 'activity');
                    self::assertNotNull($export);
                    foreach ($observed[$name] as [$raw, $verified]) {
                        self::assertSame($ids[$name], $raw['activity_id']);
                        self::assertNotNull($verified);
                        self::assertSame($export, $verified['activity_id']);
                    }
                }

                self::assertSame($scope, $storage->current());
                self::assertNull($activityStorage->current());
            });
            self::assertNull($storage->current());
            self::assertNull($activityStorage->current());
        }
    }
}
