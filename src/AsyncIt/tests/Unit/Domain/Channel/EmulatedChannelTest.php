<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Channel;

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Exception\ChannelFullException;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\Channel\EmulatedChannel
 */
final class EmulatedChannelTest extends TestCase
{
    public function testSendAndReceiveMaintainsFifoOrder(): void
    {
        $channel = new EmulatedChannel();
        $channel->send('first');
        $channel->send('second');

        self::assertSame('first', $channel->receive());
        self::assertSame('second', $channel->receive());
    }

    public function testReceiveOnEmptyThrowsChannelEmptyException(): void
    {
        $channel = new EmulatedChannel();

        $this->expectException(ChannelEmptyException::class);
        $channel->receive();
    }

    public function testSendAfterCloseThrowsChannelClosedException(): void
    {
        $channel = new EmulatedChannel();
        $channel->close();

        $this->expectException(ChannelClosedException::class);
        $channel->send('value');
    }

    public function testCloseSetsIsClosedTrue(): void
    {
        $channel = new EmulatedChannel();

        self::assertFalse($channel->isClosed());

        $channel->close();

        self::assertTrue($channel->isClosed());
    }

    public function testSendOnUnboundedChannelNeverBlocks(): void
    {
        $channel = new EmulatedChannel();

        for ($i = 0; $i < 1000; $i++) {
            $channel->send($i);
        }

        self::assertFalse($channel->isClosed());
    }

    public function testBoundedChannelRejectsExcessSend(): void
    {
        $channel = new EmulatedChannel(2);
        $channel->send(1);
        $channel->send(2);

        $this->expectException(ChannelFullException::class);
        $channel->send(3);
    }

    public function testWaitForCapacityReturnsImmediatelyWhenFull(): void
    {
        $channel = new EmulatedChannel(1);
        $channel->send('first');

        // EmulatedChannel has no event-loop integration, so waitForCapacity
        // returns immediately even when the bounded buffer is full.
        $elapsed = microtime(as_float: true);
        $channel->waitForCapacity();
        self::assertLessThan(0.1, microtime(as_float: true) - $elapsed);
    }

    public function testWaitForCapacityThrowsWhenClosed(): void
    {
        $channel = new EmulatedChannel(1);
        $channel->close();

        $this->expectException(ChannelClosedException::class);
        $channel->waitForCapacity();
    }

    public function testTrySendReturnsTrueWhenNotFull(): void
    {
        $channel = new EmulatedChannel(2);

        self::assertTrue($channel->trySend('first'));
        self::assertTrue($channel->trySend('second'));
    }

    public function testTrySendReturnsFalseWhenFull(): void
    {
        $channel = new EmulatedChannel(1);
        $channel->send('initial');

        self::assertFalse($channel->trySend('overflow'));
    }

    public function testTrySendOnUnboundedChannelAlwaysSucceeds(): void
    {
        $channel = new EmulatedChannel();

        for ($i = 0; $i < 50; $i++) {
            self::assertTrue($channel->trySend($i));
        }

        self::assertFalse($channel->isClosed());
    }

    public function testTrySendThrowsWhenClosed(): void
    {
        $channel = new EmulatedChannel(2);
        $channel->close();

        $this->expectException(ChannelClosedException::class);
        $channel->trySend('value');
    }

    public function testTrySendReusesCapacityAfterReceive(): void
    {
        $channel = new EmulatedChannel(1);
        $channel->send('a');
        self::assertSame('a', $channel->receive());

        self::assertTrue($channel->trySend('b'));
        self::assertFalse($channel->trySend('c'));
    }
}
