<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Exception\ChannelClosedException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Exception\ChannelFullException;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('channels');

echo "=== Producer/consumer over TaskChannel ===\n";

// runtime->channel() returns a TaskChannelInterface backed by the negotiated
// backend: EmulatedChannel (reference runtime) or SwooleChannel (Swoole
// adapter). The contract is identical regardless of backend.
$result = $runtime->run($scope, static function () use ($runtime): string {
    $channel = new EmulatedChannel();

    $producer = $runtime->spawn(static function () use ($runtime, $channel): void {
        for ($i = 1; $i <= 3; $i++) {
            $channel->send("message-$i");
            echo "  producer: sent message-$i\n";
            $runtime->awaitDelay(Duration::ofNanoseconds(100_000));
        }

        $channel->close();
        echo "  producer: channel closed\n";
    });

    $consumer = $runtime->spawn(static function () use ($runtime, $channel): string {
        $received = [];
        while (true) {
            try {
                $value = $channel->receive();
                $received[] = $value;
                echo "  consumer: received $value\n";
            } catch (ChannelEmptyException) {
                // EmulatedChannel is non-blocking: if closed and empty, we are done.
                if ($channel->isClosed()) {
                    break;
                }

                $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
                continue;
            }

            if ($channel->isClosed()) {
                break;
            }
        }

        return implode(', ', $received);
    });

    $runtime->await($producer);
    $runtime->awaitDelay(Duration::ofNanoseconds(200_000)); // let consumer drain

    return $runtime->await($consumer);
});

echo '  consumer collected: ', $result, "\n";

echo "\n=== trySend() non-blocking backpressure ===\n";
$result = $runtime->run($scope, static function (): int {
    $channel = new EmulatedChannel();
    $sent = 0;
    for ($i = 0; $i < 10; $i++) {
        if ($channel->trySend("item-$i")) {
            $sent++;
            echo "  trySend: item-$i accepted\n";
        } else {
            echo "  trySend: item-$i rejected (channel would block)\n";
        }
    }
    return $sent;
});

echo '  items sent via trySend: ', $result, "\n";

echo "\n=== ChannelClosedException after close() ===\n";
$runtime->run($scope, static function (): void {
    $channel = new EmulatedChannel();
    $channel->send('last');
    $channel->close();
    try {
        $channel->send('after-close');
        echo "  ERROR: send after close should have thrown\n";
    } catch (ChannelClosedException) {
        echo "  ChannelClosedException on send after close caught as expected\n";
    }
    // Drain the value sent before close.
    echo '  receive after close (draining): ', $channel->receive(), "\n";
    // EmulatedChannel throws ChannelEmptyException when closed and empty,
    // not ChannelClosedException. The contract distinguishes the two.
    try {
        $channel->receive();
        echo "  ERROR: receive from empty closed channel should have thrown\n";
    } catch (ChannelEmptyException) {
        echo "  ChannelEmptyException on receive from empty closed channel caught as expected\n";
    }
});

echo "\n=== Channel capacity and bounded buffer ===\n";
$boundedChannel = new EmulatedChannel(capacity: 2);
$boundedChannel->send('a');
$boundedChannel->send('b');
echo "  sent 'a' and 'b' to capacity-2 channel\n";

try {
    $boundedChannel->send('c');
    echo "  ERROR: send to full channel should have thrown\n";
} catch (ChannelFullException) {
    echo "  ChannelFullException caught (bounded buffer backpressure)\n";
}

echo '  receive: ', $boundedChannel->receive(), "\n";
echo '  receive: ', $boundedChannel->receive(), "\n";

try {
    $boundedChannel->receive();
    echo "  ERROR: receive from empty channel should have thrown\n";
} catch (ChannelEmptyException) {
    echo "  ChannelEmptyException caught (empty channel)\n";
}

$boundedChannel->close();
echo "  channel closed\n";
