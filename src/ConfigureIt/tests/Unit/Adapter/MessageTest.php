<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Message;
use Nette\Schema\Message as NetteMessage;

use function str_contains;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Message
 */
final class MessageTest extends Unit
{
    public function testToStringDelegatesToAdaptee(): void
    {
        $nette = new NetteMessage('Expected %value% at %path%.', NetteMessage::TypeMismatch, ['a', 'b']);
        $message = new Message($nette);

        verify(str_contains($message->toString(), 'a'))->true();
        verify(str_contains($message->toString(), 'b'))->true();
        verify($message->toString())->equals($nette->toString());
    }

    public function testMagicToStringMatchesToString(): void
    {
        $message = new Message(new NetteMessage('oops', NetteMessage::MissingItem, ['x']));

        verify((string) $message)->equals($message->toString());
    }
}
