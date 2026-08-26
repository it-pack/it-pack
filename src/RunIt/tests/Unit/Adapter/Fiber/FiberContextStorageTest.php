<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Fiber;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Fiber\FiberContextToken;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use stdClass;
use Tests\Support\Fixture\RunItTestKit;
use WeakReference;

/**
 * M11.1 — per-Fiber execution context storage.
 */
final class FiberContextStorageTest extends Unit
{
    private function kit(): RunItTestKit
    {
        return new RunItTestKit(storage: new FiberContextStorage());
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('fiber'));
    }

    public function testAttachCurrentAndDetachOnMain(): void
    {
        $storage = new FiberContextStorage();
        $scope = $this->captureScope();

        $token = $storage->attach($scope);
        self::assertSame($scope, $storage->current());

        $storage->detach($token);
        self::assertNull($storage->current());
    }

    public function testDetachingAnAlreadyDetachedTokenIsRejected(): void
    {
        $storage = new FiberContextStorage();
        $token = $storage->attach($this->captureScope());

        $storage->detach($token);

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($token);
    }

    public function testForeignTokenFromAnotherAnchorIsRejected(): void
    {
        $storage = new FiberContextStorage();
        $scope = $this->captureScope();

        $storage->attach($scope);

        $foreign = new FiberContextToken(new stdClass(), $scope);
        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($foreign);
    }

    public function testParallelFibersStayIsolated(): void
    {
        $kit = $this->kit();
        $ids = [];

        $first = new Fiber(function () use ($kit, &$ids): void {
            $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['first'] = $scope->execution()->id()->toString();
            });
        });
        $second = new Fiber(function () use ($kit, &$ids): void {
            $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['second'] = $scope->execution()->id()->toString();
            });
        });

        $first->start();
        $second->start();

        self::assertArrayHasKey('first', $ids);
        self::assertArrayHasKey('second', $ids);
        self::assertNotSame($ids['first'], $ids['second']);
        self::assertNull($kit->storage->current(), 'request-local context must not leak to main');
    }

    public function testNestedFiberRestoresParentContext(): void
    {
        $kit = $this->kit();
        $outerId = null;
        $innerId = null;
        $outerScope = null;

        $outer = new Fiber(function () use ($kit, &$outerId, &$innerId, &$outerScope): void {
            $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (
                $kit,
                &$outerId,
                &$innerId,
                &$outerScope
            ): void {
                $outerId = $scope->execution()->id()->toString();
                $outerScope = $scope;

                $inner = new Fiber(static function () use ($kit, &$innerId): void {
                    $kit->runner->run(ExecutionDefinition::create(
                        ExecutionName::fromString('inner'),
                        ExecutionKind::fromString('fiber')
                    ), static function (ExecutionScopeInterface $scope) use (&$innerId): void {
                        $innerId = $scope->execution()->id()->toString();
                    });
                });
                $inner->start();

                self::assertSame($scope, $kit->storage->current(), 'parent context restored after nested fiber');
            });
        });
        $outer->start();

        self::assertNotNull($outerId);
        self::assertNotNull($innerId);
        self::assertNotSame($outerId, $innerId);
    }

    public function testCompletedFiberStateIsReleased(): void
    {
        $storage = new FiberContextStorage();
        $ref = null;

        $fiber = new Fiber(function () use ($storage): void {
            $kit = new RunItTestKit(storage: $storage);
            $kit->runner->run($this->definition(), static fn (): null => null);
        });
        $ref = WeakReference::create($fiber);

        $fiber->start();
        $fiber = null;
        gc_collect_cycles();

        self::assertNull($ref->get(), 'completed Fiber must not be retained by the storage');
    }

    public function testMainContextIndependentOfFiber(): void
    {
        $storage = new FiberContextStorage();
        $mainScope = $this->captureScope();
        $mainToken = $storage->attach($mainScope);

        $fiber = new Fiber(function () use ($storage): void {
            $kit = new RunItTestKit(storage: $storage);
            $kit->runner->run(ExecutionDefinition::create(
                ExecutionName::fromString('fiber'),
                ExecutionKind::fromString('fiber')
            ), static fn (): null => null);
        });
        $fiber->start();

        self::assertSame($mainScope, $storage->current(), 'main context untouched by fiber work');

        $storage->detach($mainToken);
        self::assertNull($storage->current());
    }

    private function captureScope(): ExecutionScopeInterface
    {
        $kit = new RunItTestKit();
        $captured = null;
        $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (&$captured): void {
            $captured = $scope;
        });

        self::assertInstanceOf(ExecutionScopeInterface::class, $captured);

        return $captured;
    }
}
