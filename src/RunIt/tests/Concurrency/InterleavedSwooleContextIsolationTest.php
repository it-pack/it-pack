<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

use Tests\Support\Fixture\RunItTestKit;

/** Verifies runner context isolation across interleaved Swoole coroutines. */
final class InterleavedSwooleContextIsolationTest extends Unit
{
    /** Checks parentage and restoration across interleaved coroutine executions. */
    public function testNestedFailureRestoresScopeDuringInterleavingAndReuse(): void
    {
        if (!extension_loaded('swoole') || !function_exists('Swoole\\Coroutine\\run')) {
            self::markTestSkipped('Swoole coroutine extension is not available.');
        }

        $kit = new RunItTestKit(storage: new CoroutineContextStorage(new SwooleCoroutineIdProvider()));
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('interleaved.swoole'),
            ExecutionKind::fromString('task'),
        );
        $failure = new RuntimeException('nested failure');
        $ids = [];
        $completed = [];

        run(static function () use ($kit, $definition, $failure, &$ids, &$completed): void {
            $first = Coroutine::create(static function () use ($kit, $definition, $failure, &$ids, &$completed): void {
                self::assertNull($kit->storage->current());
                $kit->runner->run($definition, static function (ExecutionScopeInterface $outer) use (
                    $kit,
                    $definition,
                    $failure,
                    &$ids,
                ): void {
                    $ids[] = $outer->execution()->id()->toString();
                    self::assertNull($outer->execution()->definition()->parentExecutionId());
                    self::assertCurrentScope($kit, $outer);

                    $caught = null;
                    try {
                        $kit->runner->run($definition, static function (ExecutionScopeInterface $inner) use (
                            $kit,
                            $failure,
                            $outer,
                            &$ids,
                        ): never {
                            $ids[] = $inner->execution()->id()->toString();
                            self::assertSame(
                                $outer->execution()->id()->toString(),
                                $inner->execution()->definition()->parentExecutionId()?->toString(),
                            );
                            self::assertCurrentScope($kit, $inner);
                            Coroutine::yield();
                            self::assertCurrentScope($kit, $inner);
                            throw $failure;
                        });
                    } catch (RuntimeException $runtimeException) {
                        $caught = $runtimeException;
                    }

                    self::assertSame($failure, $caught);
                    self::assertCurrentScope($kit, $outer);
                });
                self::assertNull($kit->storage->current());

                $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use (
                    $kit,
                    &$ids,
                ): void {
                    $ids[] = $scope->execution()->id()->toString();
                    self::assertNull($scope->execution()->definition()->parentExecutionId());
                    self::assertCurrentScope($kit, $scope);
                });
                self::assertNull($kit->storage->current());
                $completed[] = 'first';
            });

            $second = Coroutine::create(static function () use ($kit, $definition, &$ids, &$completed): void {
                self::assertNull($kit->storage->current());
                $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use (
                    $kit,
                    &$ids,
                ): void {
                    $ids[] = $scope->execution()->id()->toString();
                    self::assertNull($scope->execution()->definition()->parentExecutionId());
                    self::assertCurrentScope($kit, $scope);
                    Coroutine::yield();
                    self::assertCurrentScope($kit, $scope);
                });
                self::assertNull($kit->storage->current());
                $completed[] = 'second';
            });

            self::assertNull($kit->storage->current());
            self::assertTrue(Coroutine::resume($first));
            self::assertSame(['first'], $completed);
            self::assertNull($kit->storage->current());
            self::assertTrue(Coroutine::resume($second));
            self::assertSame(['first', 'second'], $completed);
            self::assertNull($kit->storage->current());
        });

        self::assertSame(['first', 'second'], $completed);
        self::assertCount(4, array_unique($ids));
        self::assertNull($kit->storage->current());
    }

    /** Checks the current scope without assuming it is stable across coroutine switches. */
    private static function assertCurrentScope(RunItTestKit $kit, ExecutionScopeInterface $expected): void
    {
        self::assertSame($expected, $kit->storage->current());
    }
}
