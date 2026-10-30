<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Poller;

use ItPack\AsyncIt\Application\Poller\NativePoller;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Poller\NativePoller
 */
final class NativePollerAwaitTest extends TestCase
{
    public function testAwaitWithNoHandlesStillDrivesPoll(): void
    {
        $poll = new FakeNativePoll();
        $poller = new NativePoller($poll);

        $poller->await(0);

        self::assertSame(0, $poller->activeHandleCount());
        self::assertSame(1, $poll->pollCalls);
    }

    public function testAwaitSkipsPollWhenMethodMissing(): void
    {
        $poll = new class () {
            public int $pollCalls = 0;

            public function register(mixed $stream, int $events): bool
            {
                return true;
            }

            public function unregister(mixed $stream): bool
            {
                return true;
            }
        };

        $poller = new NativePoller($poll);

        $poller->await(1_000_000);

        self::assertSame(0, $poll->pollCalls);
    }
}
