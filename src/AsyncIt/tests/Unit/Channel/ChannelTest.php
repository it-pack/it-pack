<?php

declare(strict_types=1);

namespace Tests\Unit\Channel;

use ItPack\AsyncIt\Adapter\Swoole\SwooleChannel;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\Domain\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Domain\Exception\ChannelFullException;
use PHPUnit\Framework\TestCase;

final class ChannelTest extends TestCase
{
    public function testEmulatedChannelSendReceive(): void
    {
        $channel = new EmulatedChannel();

        self::assertInstanceOf(TaskChannelInterface::class, $channel);

        $channel->send(42);
        self::assertSame(42, $channel->receive());
    }

    public function testEmulatedChannelSignalsEmptiness(): void
    {
        $channel = new EmulatedChannel();

        $this->expectException(ChannelEmptyException::class);
        $channel->receive();
    }

    public function testBoundedEmulatedChannelRejectsWhenFull(): void
    {
        $channel = new EmulatedChannel(1);
        $channel->send('a');

        $this->expectException(ChannelFullException::class);
        $channel->send('b');
    }

    public function testSwooleChannelRoundTrip(): void
    {
        if (!class_exists('Swoole\\Coroutine\\Channel')) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $value = \Swoole\Coroutine\run(static function () {
            $channel = new SwooleChannel(new \Swoole\Coroutine\Channel());
            $channel->send('hello');

            return $channel->receive();
        });

        self::assertSame('hello', $value);
    }
}
