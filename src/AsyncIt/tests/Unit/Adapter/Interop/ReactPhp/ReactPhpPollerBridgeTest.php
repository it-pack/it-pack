<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Interop\ReactPhp;

use ItPack\AsyncIt\Adapter\Interop\ReactPhp\ReactPhpPollerBridge;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Interop\ReactPhp\ReactPhpPollerBridge
 */
final class ReactPhpPollerBridgeTest extends TestCase
{
    public function testRegisterTracksHandleAndAddsToLoop(): void
    {
        $loop = $this->fakeLoop();
        $bridge = new ReactPhpPollerBridge($loop);
        $stream = fopen('php://memory', 'r+');

        $handle = $bridge->register($stream);

        self::assertInstanceOf(StreamPollHandle::class, $handle);
        self::assertSame(1, $bridge->activeHandleCount());
        self::assertCount(1, $loop->added);
    }

    public function testReleaseRemovesFromLoop(): void
    {
        $loop = $this->fakeLoop();
        $bridge = new ReactPhpPollerBridge($loop);
        $stream = fopen('php://memory', 'r+');
        $handle = $bridge->register($stream);

        $bridge->release($handle);

        self::assertSame(0, $bridge->activeHandleCount());
        self::assertCount(1, $loop->removed);
    }

    public function testAwaitProbesReadinessWithoutError(): void
    {
        $bridge = new ReactPhpPollerBridge($this->fakeLoop());
        $stream = fopen('php://memory', 'r+');
        $bridge->register($stream);

        $bridge->await(0);

        self::assertSame(1, $bridge->activeHandleCount());
    }
    private function fakeLoop(): FakeReactLoop
    {
        return new FakeReactLoop();
    }
}

final class FakeReactLoop
{
    /** @var list<mixed> */
    public array $added = [];

    /** @var list<mixed> */
    public array $removed = [];

    public function addReadStream(mixed $stream, callable $callback): void
    {
        $this->added[] = $stream;
    }

    public function removeReadStream(mixed $stream): void
    {
        $this->removed[] = $stream;
    }
}
