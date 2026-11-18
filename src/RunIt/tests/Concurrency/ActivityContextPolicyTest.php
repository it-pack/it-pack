<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberActivityContextStorage;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

final class ActivityContextPolicyTest extends Unit
{
    public function testMissingAndMismatchedExecutionContextsFollowPolicy(): void
    {
        foreach (['fallback', 'drop', 'throw'] as $policy) {
            foreach ([false, true] as $mismatched) {
                $storage = new FiberContextStorage();
                $authority = new ProvenanceAuthority();
                $kit = new RunItTestKit(
                    storage: $storage,
                    authority: $authority,
                    activityContextStorage: new FiberActivityContextStorage(),
                    activityContextPolicy: $policy,
                );
                $definition = ExecutionDefinition::create(
                    ExecutionName::fromString('test'),
                    ExecutionKind::fromString('task'),
                );
                $kit->runner->run($definition, static function (ExecutionScopeInterface $owner) use (
                    $kit,
                    $definition,
                    $storage,
                    $authority,
                    $policy,
                    $mismatched,
                ): void {
                    $kit->runner->run($definition, static function (ExecutionScopeInterface $other) use (
                        $kit,
                        $owner,
                        $storage,
                        $authority,
                        $policy,
                        $mismatched,
                    ): void {
                        $fiber = new Fiber(static function () use (
                            $kit,
                            $owner,
                            $other,
                            $storage,
                            $authority,
                            $policy,
                            $mismatched,
                        ): void {
                            $token = $mismatched ? $storage->attach($other) : null;
                            try {
                                $before = count($kit->artifacts());
                                if ($policy === 'throw') {
                                    try {
                                        $owner->observations()->record('secret payload');
                                        self::fail('Expected a context failure.');
                                    } catch (RuntimeException $failure) {
                                        self::assertStringNotContainsString('secret payload', $failure->getMessage());
                                    }

                                    self::assertCount($before, $kit->artifacts());

                                    return;
                                }

                                $observation = $owner->observations()->record('secret payload');
                                self::assertNull($observation->normalize()['activity_id']);
                                if ($policy === 'drop') {
                                    self::assertCount($before, $kit->artifacts());
                                    self::assertNull($authority->exportIdentityFields($observation));
                                } else {
                                    self::assertCount($before + 1, $kit->artifacts());
                                    $fields = $authority->exportIdentityFields($observation);
                                    self::assertNotNull($fields);
                                    self::assertArrayNotHasKey('activity_id', $fields);
                                }
                            } finally {
                                if ($token instanceof ContextTokenInterface) {
                                    $storage->detach($token);
                                }
                            }
                        });
                        $fiber->start();
                    });
                });
            }
        }
    }
}
