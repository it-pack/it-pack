<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberActivityContextStorage;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use ItPack\RunIt\Adapter\Swoole\CoroutineActivityContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Application\Propagation\CompositeActivityContextStorage;
use ItPack\RunIt\Application\Propagation\StackActivityContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;

final class ActivityContextStorageTest extends Unit
{
    public function testNestingAndForeignTokensDoNotAlterExecutionStorage(): void
    {
        $execution = new StackContextStorage();
        $storage = new StackActivityContextStorage();
        $other = new StackActivityContextStorage();
        $outer = self::createStub(ActivityScopeInterface::class);
        $inner = self::createStub(ActivityScopeInterface::class);
        $executionToken = $execution->attach($outer);
        $outerToken = $storage->attach($outer);
        $innerToken = $storage->attach($inner);
        $foreign = $other->attach($inner);

        foreach ([$outerToken, $foreign] as $token) {
            try {
                $storage->detach($token);
                self::fail('Invalid token accepted.');
            } catch (InvalidContextTokenException) {
                self::assertSame($inner, $storage->current());
            }
        }

        $storage->detach($innerToken);
        self::assertSame($outer, $storage->current());
        $storage->detach($outerToken);
        self::assertNull($storage->current());
        self::assertSame($outer, $execution->current());
        $execution->detach($executionToken);
        $other->detach($foreign);
    }

    public function testFiberRequiresExplicitPropagationAndRestoresContext(): void
    {
        $storage = new FiberActivityContextStorage();
        $scope = self::createStub(ActivityScopeInterface::class);
        $token = $storage->attach($scope);
        $fiber = new Fiber(static function () use ($storage, $scope): void {
            self::assertNull($storage->current());
            $fiberToken = $storage->attach($scope);
            Fiber::suspend();
            self::assertSame($scope, $storage->current());
            $storage->detach($fiberToken);
            self::assertNull($storage->current());
        });
        $fiber->start();
        self::assertSame($scope, $storage->current());
        $fiber->resume();
        $storage->detach($token);
    }

    public function testCoroutineIsolationAndCompositeSelection(): void
    {
        $provider = new class () implements CoroutineIdProviderInterface {
            public int $id = -1;

            public function currentCoroutineId(): int
            {
                return $this->id;
            }
        };
        $detector = new readonly class ($provider) implements RuntimeContextDetectorInterface {
            public function __construct(private CoroutineIdProviderInterface $provider)
            {
            }

            public function inCoroutine(): bool
            {
                return $this->provider->currentCoroutineId() >= 0;
            }

            public function inFiber(): bool
            {
                return Fiber::getCurrent() instanceof Fiber;
            }
        };
        $scope = self::createStub(ActivityScopeInterface::class);
        $coroutine = new CoroutineActivityContextStorage($provider);
        $composite = new CompositeActivityContextStorage(
            new StackContextStorage(),
            new FiberContextStorage(),
            new CoroutineContextStorage($provider),
            $detector,
        );
        foreach ([$coroutine, $composite] as $storage) {
            $mainToken = $storage->attach($scope);
            $provider->id = 1;
            self::assertNull($storage->current());
            $token = $storage->attach($scope);
            $provider->id = 2;
            self::assertNull($storage->current());
            $provider->id = 1;
            self::assertSame($scope, $storage->current());
            $storage->detach($token);
            $provider->id = -1;
            self::assertSame($scope, $storage->current());
            $storage->detach($mainToken);
        }
    }
}
