<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwoolePoller
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage
 */
final class SwooleAdapterTest extends TestCase
{
    public function testPollerRegisterAndRelease(): void
    {
        $poller = new SwoolePoller();
        $stream = fopen('php://memory', 'rb+');

        $handle = $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());

        $poller->release($handle);

        self::assertSame(0, $poller->activeHandleCount());

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testPollerAwaitWithNoHandlesDoesNotError(): void
    {
        $poller = new SwoolePoller();

        $poller->await(0);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testStorageAttachAndCurrentFallback(): void
    {
        $storage = new SwooleCoroutineContextStorage(
            new FiberScopeSlotFactory(),
        );

        self::assertNull($storage->current());

        $scope = new ReferenceExecutionScope('test');
        $token = $storage->attach($scope);

        self::assertSame($scope, $storage->current());

        $storage->detach($token);

        self::assertNull($storage->current());
    }
}
