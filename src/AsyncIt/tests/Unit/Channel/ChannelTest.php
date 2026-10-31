<?php

declare(strict_types=1);

namespace Tests\Unit\Channel;

use ItPack\AsyncIt\Adapter\Swoole\SwooleChannel;
use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Exception\ChannelFullException;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

/**
 * @covers \ItPack\AsyncIt\Domain\Channel\EmulatedChannel
 * @covers \ItPack\AsyncIt\Adapter\Swoole\SwooleChannel
 */
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

    public function testBoundedEmulatedChannelReusesCapacityAfterReceive(): void
    {
        $channel = new EmulatedChannel(2);
        $channel->send('a');
        $channel->send('b');
        self::assertSame('a', $channel->receive());

        $channel->send('c');
        self::assertSame('b', $channel->receive());
        self::assertSame('c', $channel->receive());
    }

    public function testBoundedEmulatedChannelCapacityAccountsForHeadWithoutCompaction(): void
    {
        $channel = new EmulatedChannel(3);
        $channel->send('a');
        $channel->send('b');
        $channel->send('c');
        self::assertSame('a', $channel->receive());

        $channel->send('d');
        self::assertSame('b', $channel->receive());
        self::assertSame('c', $channel->receive());
        self::assertSame('d', $channel->receive());
    }

    public function testEmulatedChannelSendThrowsAfterClose(): void
    {
        $channel = new EmulatedChannel();
        $channel->close();

        $this->expectException(ChannelClosedException::class);
        $channel->send('after-close');
    }

    public function testSwooleChannelRoundTrip(): void
    {
        if (!class_exists(Channel::class)) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $value = null;

        run(static function () use (&$value): void {
            $channel = new SwooleChannel(new Channel());
            $channel->send('hello');

            $value = $channel->receive();
        });

        self::assertSame('hello', $value);
    }

    public function testSwooleChannelWaitForCapacityBlocksOnFull(): void
    {
        if (!class_exists(Channel::class)) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $received = null;

        run(static function () use (&$received): void {
            $channel = new SwooleChannel(new Channel(1));
            $channel->send('initial');

            // Producer coroutine: waitForCapacity blocks until consumer frees space
            go(static function () use ($channel): void {
                $channel->waitForCapacity();
                $channel->send('after-wait');
            });

            // Consumer coroutine: yields, then pops, freeing a slot
            go(static function () use ($channel, &$received): void {
                Coroutine::sleep(0.01);
                $channel->receive(); // pop 'initial'
                Coroutine::sleep(0.01);
                $received = $channel->receive(); // should get 'after-wait'
            });
        });

        self::assertSame('after-wait', $received);
    }

    public function testSwooleChannelSendThrowsAfterClose(): void
    {
        if (!class_exists(Channel::class)) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $threw = false;

        run(static function () use (&$threw): void {
            $channel = new SwooleChannel(new Channel());
            $channel->close();

            try {
                $channel->send('after-close');
            } catch (ChannelClosedException) {
                $threw = true;
            }
        });

        self::assertTrue($threw);
    }
}
