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
}
