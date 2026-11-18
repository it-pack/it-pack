<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\CoroutineActivityContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

use Tests\Support\Fixture\RunItTestKit;

/** Verifies activity attribution for overlapping real Swoole coroutines. */
final class OverlappingSwooleActivityProvenanceTest extends Unit
{
    /** Checks both completion orders and cleanup of explicitly propagated execution context. */
    public function testOverlappingActivitiesRetainTheirOwnAttribution(): void
    {
        if (!extension_loaded('swoole') || !function_exists('Swoole\\Coroutine\\run')) {
            self::markTestSkipped('Swoole coroutine extension is not available.');
        }

        foreach ([['first', 'second'], ['second', 'first']] as $order) {
            $provider = new SwooleCoroutineIdProvider();
            $storage = new CoroutineContextStorage($provider);
            $activityStorage = new CoroutineActivityContextStorage($provider);
            $authority = new ProvenanceAuthority();
            $kit = new RunItTestKit(storage: $storage, authority: $authority, activityContextStorage: $activityStorage);
            $execution = ExecutionDefinition::create(
                ExecutionName::fromString('test'),
                ExecutionKind::fromString('task'),
            );
            $activity = ActivityDefinition::create(ActivityName::fromString('test'), ActivityKind::fromString('task'));

            run(static function () use (
                $kit,
                $execution,
                $activity,
                $storage,
                $activityStorage,
                $authority,
                $order,
            ): void {
                $kit->runner->run($execution, static function (ExecutionScopeInterface $scope) use (
                    $activity,
                    $storage,
                    $activityStorage,
                    $authority,
                    $order,
                ): void {
                    $ids = [];
                    $observed = [];
                    $completed = [];
                    $coroutines = [];
                    foreach (['first', 'second'] as $name) {
                        $coroutines[$name] = Coroutine::create(static function () use (
                            $scope,
                            $activity,
                            $storage,
                            $activityStorage,
                            $authority,
                            $name,
                            &$ids,
                            &$observed,
                            &$completed,
                        ): void {
                            self::assertNull($storage->current());
                            self::assertNull($activityStorage->current());
                            $token = $storage->attach($scope);
                            try {
                                $scope->activities()->run(
                                    $activity,
                                    static function (ActivityScopeInterface $active) use (
                                        $scope,
                                        $storage,
                                        $authority,
                                        $name,
                                        &$ids,
                                        &$observed,
                                    ): void {
                                        $ids[$name] = $active->activity()->activityId()?->toString();
                                        Coroutine::yield();
                                        self::assertSame($scope, $storage->current());
                                        foreach ([$scope, $active] as $recorderScope) {
                                            $observation = $recorderScope->observations()->record($name);
                                            $observed[$name][] = [
                                                $observation->normalize(),
                                                $authority->exportIdentityFields($observation),
                                            ];
                                        }
                                    },
                                );
                                self::assertSame($scope, $storage->current());
                                self::assertNull($activityStorage->current());
                                $after = $scope->observations()->record('after');
                                self::assertNull($after->normalize()['activity_id']);
                                $fields = $authority->exportIdentityFields($after);
                                self::assertNotNull($fields);
                                self::assertArrayNotHasKey('activity_id', $fields);
                            } finally {
                                $storage->detach($token);
                            }

                            self::assertNull($storage->current());
                            self::assertNull($activityStorage->current());
                            $completed[] = $name;
                        });
                    }

                    self::assertCount(2, $ids);
                    self::assertSame($scope, $storage->current());
                    self::assertNull($activityStorage->current());
                    foreach ($order as $name) {
                        self::assertTrue(Coroutine::resume($coroutines[$name]));
                    }

                    self::assertSame($order, $completed);
                    self::assertNotNull($ids['first']);
                    self::assertNotNull($ids['second']);
                    self::assertNotSame($ids['first'], $ids['second']);
                    foreach (['first', 'second'] as $name) {
                        self::assertNotNull($ids[$name]);
                        $export = $authority->exportIdForBusinessId(
                            $ids[$name],
                            $scope->execution()->id()->toString(),
                            'activity',
                        );
                        self::assertNotNull($export);
                        self::assertCount(2, $observed[$name]);
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
            });
            self::assertNull($storage->current());
            self::assertNull($activityStorage->current());
        }
    }
}
