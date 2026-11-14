<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Revolt;

use ItPack\AsyncIt\Adapter\Revolt\RevoltPoller;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Revolt\RevoltPoller
 */
final class RevoltPollerTest extends TestCase
{
    public function testRegisterCreatesHandleAndTracksCount(): void
    {
        $poller = new RevoltPoller();
        $stream = fopen('php://memory', 'rb+');
        self::assertIsResource($stream);

        $handle = $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());
        self::assertSame('revolt-1', $handle->id);
    }

    public function testReleaseClearsHandle(): void
    {
        $poller = new RevoltPoller();
        $stream = fopen('php://memory', 'rb+');
        $handle = $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());

        $poller->release($handle);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testReleaseWithoutReactorRegistrationStillClearsHandle(): void
    {
        $poller = new RevoltPoller();
        $stream = fopen('php://memory', 'rb+');
        $handle = $poller->register($stream);

        self::assertSame(1, $poller->activeHandleCount());

        $poller->release($handle);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testAwaitWithNoHandlesDoesNotError(): void
    {
        $poller = new RevoltPoller();

        $poller->await(0);

        self::assertSame(0, $poller->activeHandleCount());
    }

    public function testReadyHandlesReturnsReadyStreams(): void
    {
        $poller = new RevoltPoller();
        $pair = stream_socket_pair(STREAM_PF_UNIX, SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsResource($pair[0]);
        self::assertIsResource($pair[1]);

        fwrite($pair[0], 'ready');

        $handle = $poller->register($pair[1]);

        $poller->await(0);

        $ready = $poller->readyHandles();

        try {
            self::assertCount(1, $ready);
            self::assertSame($handle->id, $ready[0]->id);
        } finally {
            $poller->release($handle);
            fclose($pair[0]);
            fclose($pair[1]);
        }
    }
}
