<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage
 */
final class SwooleContextStorageTest extends TestCase
{
    public function testImplementsContextStorageInterface(): void
    {
        $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());

        self::assertInstanceOf(ContextStorageInterface::class, $storage);
    }

    public function testCurrentReturnsNullInitially(): void
    {
        $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());

        self::assertNull($storage->current());
    }

    public function testAttachReturnsTokenAndCurrentReturnsScope(): void
    {
        $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());
        $scope = $this->createMock(\ItPack\RunIt\Contract\Application\ExecutionScopeInterface::class);

        $token = $storage->attach($scope);

        self::assertInstanceOf(ContextTokenInterface::class, $token);
        self::assertSame($scope, $storage->current());
    }

    public function testDetachClearsCurrentScope(): void
    {
        $storage = new SwooleCoroutineContextStorage(new FiberScopeSlotFactory());
        $scope = $this->createMock(\ItPack\RunIt\Contract\Application\ExecutionScopeInterface::class);

        $token = $storage->attach($scope);
        $storage->detach($token);

        self::assertNull($storage->current());
    }
}
