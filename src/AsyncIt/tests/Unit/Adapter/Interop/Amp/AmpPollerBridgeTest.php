<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Interop\Amp;

use ItPack\AsyncIt\Adapter\Interop\Amp\AmpPollerBridge;
use ItPack\AsyncIt\Domain\StreamPollHandle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Adapter\Interop\Amp\AmpPollerBridge
 */
final class AmpPollerBridgeTest extends TestCase
{
    public function testRegisterTracksHandle(): void
    {
        $bridge = new AmpPollerBridge();
        $stream = fopen('php://memory', 'r+');

        $handle = $bridge->register($stream);

        self::assertInstanceOf(StreamPollHandle::class, $handle);
        self::assertSame(1, $bridge->activeHandleCount());
    }

    public function testReleaseClearsHandle(): void
    {
        $bridge = new AmpPollerBridge();
        $stream = fopen('php://memory', 'r+');
        $handle = $bridge->register($stream);

        $bridge->release($handle);

        self::assertSame(0, $bridge->activeHandleCount());
    }

    public function testAwaitProbesReadinessWithoutError(): void
    {
        $bridge = new AmpPollerBridge();
        $stream = fopen('php://memory', 'r+');
        $bridge->register($stream);

        $bridge->await(0);

        self::assertSame(1, $bridge->activeHandleCount());
    }
}
