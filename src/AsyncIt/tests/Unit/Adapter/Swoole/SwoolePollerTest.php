<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwoolePoller
 */
final class SwoolePollerTest extends TestCase
{
    public function testRegisterCreatesHandleAndTracksCount(): void
    {
        $poller = new SwoolePoller();
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $handle = $poller->register($stream);

        self::assertInstanceOf(StreamPollHandle::class, $handle);
        self::assertSame(1, $poller->activeHandleCount());
    }

    public function testReleaseClearsHandle(): void
    {
        $poller = new SwoolePoller();
        $stream = fopen('php://memory', 'r+');
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
}
