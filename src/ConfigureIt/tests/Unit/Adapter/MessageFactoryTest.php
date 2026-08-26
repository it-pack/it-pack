<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Message;
use ItPack\ConfigureIt\Adapter\MessageFactory;
use Nette\Schema\Message as NetteMessage;

use function str_contains;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Message
 * @covers \ItPack\ConfigureIt\Adapter\MessageFactory
 * @covers \ItPack\ConfigureIt\Behavior\CreatableBehavior
 */
final class MessageFactoryTest extends Unit
{
    public function testBuildWrapsNetteMessage(): void
    {
        $message = new MessageFactory()->build(
            'Expected %value% at %path%.',
            NetteMessage::TypeMismatch,
            ['db', 'host'],
            ['value' => 1, 'expected' => 'string'],
        );

        self::assertInstanceOf(Message::class, $message);
        verify(str_contains($message->toString(), 'db'))->true();
        verify(str_contains($message->toString(), 'host'))->true();
        verify(str_contains($message->toString(), '1'))->true();
        verify($message->__toString())->equals($message->toString());
    }

    public function testBuildFromNetteMessage(): void
    {
        $nette = new NetteMessage('found %path%', NetteMessage::MissingItem, ['a']);
        $message = new MessageFactory()->buildFromNetteMessage($nette);

        self::assertInstanceOf(Message::class, $message);
        verify($message->adaptee)->same($nette);
        verify(str_contains($message->toString(), "'a'"))->true();
    }

    public function testCreateReturnsFactory(): void
    {
        self::assertInstanceOf(MessageFactory::class, MessageFactory::create());
    }
}
