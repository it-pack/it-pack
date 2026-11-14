<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Fiber;
use ItPack\AsyncIt\Adapter\Revolt\RevoltFiberContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;
use ItPack\AsyncIt\Domain\ContextToken;
use ItPack\AsyncIt\Domain\FiberScopeSlotInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

use WeakMap;
use WeakReference;

final class ContextStorageOwnershipTest extends TestCase
{
    /** @return iterable<string, array{callable(): ContextStorageInterface}> */
    public static function storages(): iterable
    {
        yield 'memory' => [static fn (): MemoryContextStorage => new MemoryContextStorage()];
        yield 'revolt' => [static function (): RevoltFiberContextStorage {
            /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlotInterface> $slots */
            $slots = new WeakMap();

            return new RevoltFiberContextStorage($slots, new FiberScopeSlotFactory());
        }];
        yield 'swoole' => [static fn (): SwooleCoroutineContextStorage => new SwooleCoroutineContextStorage(new FiberScopeSlotFactory())];
        yield 'swow' => [static fn (): SwowCoroutineContextStorage => new SwowCoroutineContextStorage(new FiberScopeSlotFactory())];
    }

    /**
     * @dataProvider storages
     * @param callable(): ContextStorageInterface $create
     */
    public function testCollidingForeignAndForgedTokensCannotDetach(callable $create): void
    {
        $storage = $create();
        $other = $create();
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $token = $storage->attach($scope);
        $foreign = $other->attach($this->createMock(ExecutionScopeInterface::class));
        self::assertSame($token->id(), $foreign->id());

        foreach ([$foreign, new ContextToken($token->id())] as $invalid) {
            try {
                $storage->detach($invalid);
                self::fail('A foreign or forged token must not detach the current scope.');
            } catch (LogicException) {
                self::assertSame($scope, $storage->current());
            }
        }

        $storage->detach($token);
        self::assertNull($storage->current());
        $other->detach($foreign);
        self::assertNull($other->current());
    }

    /**
     * @dataProvider storages
     * @param callable(): ContextStorageInterface $create
     */
    public function testOutOfOrderAndRepeatedDetachLeaveScopeIntact(callable $create): void
    {
        $storage = $create();
        $outer = $storage->attach($this->createMock(ExecutionScopeInterface::class));
        $innerScope = $this->createMock(ExecutionScopeInterface::class);
        $inner = $storage->attach($innerScope);

        try {
            $storage->detach($outer);
            self::fail('Out-of-order detach must fail.');
        } catch (LogicException) {
            self::assertSame($innerScope, $storage->current());
        }

        $storage->detach($inner);
        try {
            $storage->detach($inner);
            self::fail('Repeated detach must fail.');
        } catch (LogicException) {
            self::assertNotNull($storage->current());
        }

        $storage->detach($outer);
        self::assertNull($storage->current());
    }

    public function testRevoltRejectsTokenFromAnotherActiveFiber(): void
    {
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlotInterface> $slots */
        $slots = new WeakMap();
        $storage = new RevoltFiberContextStorage($slots, new FiberScopeSlotFactory());
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $outer = $storage->attach($scope);
        $fiber = new Fiber(function () use ($storage, $outer): void {
            $innerScope = $this->createMock(ExecutionScopeInterface::class);
            $inner = $storage->attach($innerScope);
            try {
                $storage->detach($outer);
                self::fail('A token from another slot must not detach.');
            } catch (LogicException) {
                self::assertSame($innerScope, $storage->current());
            }

            $storage->detach($inner);
            self::assertNull($storage->current());
        });
        $fiber->start();
        self::assertSame($scope, $storage->current());
        $storage->detach($outer);
    }

    public function testSwooleRejectsForeignTokenInsideCoroutine(): void
    {
        if (
            !class_exists(Coroutine::class)
            || !function_exists('Swoole\\Coroutine\\run')
        ) {
            self::markTestSkipped('Swoole is not installed.');
        }

        run(function (): void {
            $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());
            $other = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());
            $scope = $this->createMock(ExecutionScopeInterface::class);
            $token = $storage->attach($scope);
            $foreign = $other->attach($this->createMock(ExecutionScopeInterface::class));
            self::assertSame($token->id(), $foreign->id());
            try {
                $storage->detach($foreign);
                self::fail('A foreign token must not detach the current scope.');
            } catch (LogicException) {
                self::assertSame($scope, $storage->current());
            }

            $storage->detach($token);
            $other->detach($foreign);
        });
    }

    public function testAbandonedCoroutineTokenIsNotRetained(): void
    {
        $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $token = $storage->attach($scope);
        $id = $token->id();
        $reference = WeakReference::create($token);
        unset($token);
        self::assertNull($reference->get());

        $next = $storage->attach($scope);
        $storage->detach($next);
        try {
            $storage->detach(new ContextToken($id));
            self::fail('An abandoned token cannot be replaced by an equal ID.');
        } catch (LogicException) {
            self::assertSame($scope, $storage->current());
        }
    }
}
