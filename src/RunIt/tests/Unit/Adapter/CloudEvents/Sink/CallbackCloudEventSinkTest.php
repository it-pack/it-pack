<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Sink;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Contract\Adapter\CloudEvents\CloudEventInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(CallbackCloudEventSink::class)]
final class CallbackCloudEventSinkTest extends Unit
{
    /**
     * @throws Throwable
     */
    public function testSendForwardsToCallback(): void
    {
        $received = null;
        $sink = new CallbackCloudEventSink(function (CloudEventInterface $event) use (&$received): void {
            $received = $event;
        });
        $event = $this->createMock(CloudEventInterface::class);

        $sink->send($event);

        self::assertInstanceOf(CloudEventInterface::class, $received);
        self::assertSame($event, $received);
    }
}
