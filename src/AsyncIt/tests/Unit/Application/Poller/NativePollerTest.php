<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Poller;

use ItPack\AsyncIt\Application\Poller\NativePoller;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Poller\NativePoller
 */
final class NativePollerTest extends TestCase
{
    public function testRegisterTracksHandleAndRegistersWithPoll(): void
    {
        $poll = $this->fakePoll();
        $poller = new NativePoller($poll);
        $stream = fopen('php://memory', 'r+');

        $handle = $poller->register($stream);

        self::assertInstanceOf(StreamPollHandle::class, $handle);
        self::assertSame(1, $poller->activeHandleCount());
        self::assertCount(1, $poll->registered);
        self::assertSame($stream, $poll->registered[0]);
    }

    public function testReleaseDetachesWatcherAndClearsHandle(): void
    {
        $poll = $this->fakePoll();
        $poller = new NativePoller($poll);
        $stream = fopen('php://memory', 'r+');
        $handle = $poller->register($stream);

        $poller->release($handle);

        self::assertSame(0, $poller->activeHandleCount());
        self::assertCount(1, $poll->unregistered);
        self::assertSame($stream, $poll->unregistered[0]);
    }

    public function testAwaitDrivesNativePoll(): void
    {
        $poll = $this->fakePoll();
        $poller = new NativePoller($poll);

        $poller->await(1_500_000);

        self::assertSame(1, $poll->pollCalls);
    }
    private function fakePoll(): FakeNativePoll
    {
        return new FakeNativePoll();
    }
}

final class FakeNativePoll
{
    /** @var list<mixed> */
    public array $registered = [];

    /** @var list<mixed> */
    public array $unregistered = [];

    public int $pollCalls = 0;

    public function register(mixed $stream, int $events): bool
    {
        $this->registered[] = $stream;

        return true;
    }

    public function unregister(mixed $stream): bool
    {
        $this->unregistered[] = $stream;

        return true;
    }

    /**
     * @return array<int, mixed>
     */
    public function poll(int $timeout): array
    {
        $this->pollCalls++;

        return [];
    }
}
