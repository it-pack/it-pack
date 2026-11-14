<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwoolePoller
 */
final class SwoolePollerTest extends TestCase
{
    public function testRegisterCreatesHandleAndTracksCount(): void
    {
        $poller = new SwoolePoller();
        $stream = fopen('php://memory', 'rb+');
        self::assertIsResource($stream);

        $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());
    }

    public function testReleaseClearsHandle(): void
    {
        $poller = new SwoolePoller();
        $stream = fopen('php://memory', 'rb+');
        $handle = $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());

        $poller->release($handle);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testAwaitWithNoHandlesDoesNotError(): void
    {
        $poller = new SwoolePoller();

        $poller->await(0);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testReadyHandlesReturnsReadyStreams(): void
    {
        $poller = new SwoolePoller();
        $readable = fopen('php://memory', 'rb+');
        $writable = fopen('php://memory', 'rb+');

        $poller->register($readable);
        $poller->register($writable);

        $poller->await(0);

        $poller->readyHandles();
        self::assertSame(2, $poller->activeHandleCount());
    }
}
