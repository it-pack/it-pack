<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Sink;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Contract\Adapter\CloudEvents\CloudEventInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(CallbackCloudEventSink::class)]
final class CallbackCloudEventSinkTest extends Unit
{
    public function testRejectsNonCallable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CallbackCloudEventSink('not a callable');
    }

    /**
     * @throws Throwable
     */
    public function testSendForwardsToClosure(): void
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

    /**
     * @throws Throwable
     */
    public function testSendForwardsToArrayCallable(): void
    {
        $receiver = new class () {
            public ?CloudEventInterface $event = null;

            public function receive(CloudEventInterface $event): void
            {
                $this->event = $event;
            }
        };
        $sink = new CallbackCloudEventSink($receiver->receive(...));
        $event = $this->createMock(CloudEventInterface::class);

        $sink->send($event);

        self::assertSame($event, $receiver->event);
    }

    /**
     * @throws Throwable
     */
    public function testSendForwardsToInvokableObject(): void
    {
        $invokable = new class () {
            public ?CloudEventInterface $event = null;

            public function __invoke(CloudEventInterface $event): void
            {
                $this->event = $event;
            }
        };
        $sink = new CallbackCloudEventSink($invokable);
        $event = $this->createMock(CloudEventInterface::class);

        $sink->send($event);

        self::assertSame($event, $invokable->event);
    }
}
