<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Interleaved fiber context isolation.
 *
 * Fibers attach and suspend while holding a scope, then another fiber attaches
 * its own scope while the first is suspended. Each fiber must observe only its
 * own ambient scope when resumed, and no scope must leak across fibers or to
 * the main stack.
 */
final class InterleavedFiberContextIsolationTest extends Unit
{
    public function testSynchronousDefaultSharesAmbientScopeAcrossFibers(): void
    {
        $kit = new RunItTestKit();
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('interleaved.default'),
            ExecutionKind::fromString('task'),
        );
        $firstScope = null;
        $first = new Fiber(static function () use ($kit, $definition, &$firstScope): void {
            $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use (&$firstScope): void {
                $firstScope = $scope;
                Fiber::suspend();
            });
        });
        $first->start();
        self::assertSame($firstScope, $kit->storage->current());

        $second = new Fiber(static function () use ($kit, $definition, &$firstScope): void {
            self::assertSame($firstScope, $kit->storage->current());
            $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use (&$firstScope): void {
                self::assertInstanceOf(ExecutionScopeInterface::class, $firstScope);
                self::assertSame(
                    $firstScope->execution()->id()->toString(),
                    $scope->execution()->definition()->parentExecutionId()?->toString(),
                );
            });
        });
        $second->start();
        self::assertSame($firstScope, $kit->storage->current());
        $first->resume();
        self::assertNull($kit->storage->current());
    }

    public function testInterleavedFibersNeverShareAmbientScope(): void
    {
        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withContextStorage(new FiberContextStorage())
            ->build()
        ;
        $definition = static fn (): ExecutionDefinition => ExecutionDefinition::create(
            ExecutionName::fromString('interleaved.fiber'),
            ExecutionKind::fromString('task'),
        );

        $results = [];
        $fibers = [];
        $initialAmbients = [];

        foreach (range(1, 6) as $index) {
            $fibers[$index] = new Fiber(static function () use ($runtime, $definition, $index, &$results) {
                return $results[$index] = $runtime->run($definition(), static function () use ($runtime): array {
                    $ambient = $runtime->currentExecution()?->execution()->id()->toString();

                    // Suspend while holding the ambient scope, giving another
                    // fiber a chance to attach its own scope.
                    Fiber::suspend(['ambient' => $ambient]);

                    // After resume, the ambient scope must still be this fiber's.
                    return [
                        'ambient' => $runtime->currentExecution()?->execution()->id()->toString(),
                    ];
                });
            });

            $initial = $fibers[$index]->start();
            self::assertIsArray($initial);
            self::assertIsString($initial['ambient']);
            $initialAmbients[$index] = $initial['ambient'];
            self::assertNull($runtime->currentExecution());
        }

        $seen = [];

        foreach ($fibers as $index => $fiber) {
            while (!$fiber->isTerminated()) {
                $fiber->resume();
            }

            $seen[$index] = $fiber->getReturn();
        }

        $ambients = [];

        foreach ($seen as $index => $result) {
            self::assertIsArray($result, "fiber {$index} must return a result");
            self::assertArrayHasKey('ambient', $result, "fiber {$index} must observe its own ambient scope");
            self::assertSame($initialAmbients[$index], $result['ambient']);
            $ambients[$index] = $result['ambient'];
        }

        self::assertCount(6, array_unique($ambients), 'interleaved fibers must not share an ambient execution');
        self::assertNull($runtime->currentExecution());
    }

    public function testNestedFailureRestoresFiberScopeDuringInterleavingAndReuse(): void
    {
        $kit = new RunItTestKit(storage: new FiberContextStorage());
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('interleaved.failure'),
            ExecutionKind::fromString('task'),
        );
        $failure = new RuntimeException('nested failure');
        $ids = [];

        $first = new Fiber(static function () use ($kit, $definition, $failure, &$ids): void {
            self::assertNull($kit->storage->current());
            $kit->runner->run($definition, static function (ExecutionScopeInterface $outer) use (
                $kit,
                $definition,
                $failure,
                &$ids,
            ): void {
                $ids[] = $outer->execution()->id()->toString();
                self::assertSame($outer, $kit->storage->current());

                try {
                    $kit->runner->run($definition, static function (ExecutionScopeInterface $inner) use (
                        $kit,
                        $failure,
                        $outer,
                        &$ids,
                    ): void {
                        $ids[] = $inner->execution()->id()->toString();
                        self::assertSame(
                            $outer->execution()->id()->toString(),
                            $inner->execution()->definition()->parentExecutionId()?->toString(),
                        );
                        self::assertSame($inner, $kit->storage->current());
                        Fiber::suspend();
                        self::assertSame($inner, $kit->storage->current());
                        throw $failure;
                    });
                    self::fail('The nested operation must throw');
                } catch (RuntimeException $caught) {
                    self::assertSame($failure, $caught);
                }

                self::assertSame($outer, $kit->storage->current());
            });
            self::assertNull($kit->storage->current());

            $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use ($kit, &$ids): void {
                $ids[] = $scope->execution()->id()->toString();
                self::assertSame($scope, $kit->storage->current());
            });
            self::assertNull($kit->storage->current());
        });

        $second = new Fiber(static function () use ($kit, $definition, &$ids): void {
            self::assertNull($kit->storage->current());
            $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use ($kit, &$ids): void {
                $ids[] = $scope->execution()->id()->toString();
                self::assertSame($scope, $kit->storage->current());
                Fiber::suspend();
                self::assertSame($scope, $kit->storage->current());
            });
            self::assertNull($kit->storage->current());
        });

        $first->start();
        $second->start();
        self::assertNull($kit->storage->current());
        $first->resume();
        self::assertTrue($first->isTerminated());
        self::assertNull($kit->storage->current());
        $second->resume();
        self::assertTrue($second->isTerminated());
        self::assertNull($kit->storage->current());
        self::assertCount(4, array_unique($ids));
    }
}
