<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\MessageInterface;
use ItPack\ConfigureIt\Contract\ValidationExceptionInterface;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\ValueObject\ValidationMessage;

/**
 * F3 — ValidationException must implement ValidationExceptionInterface and carry
 * structured MessageInterface objects, not just a string.
 *
 * @covers \ItPack\ConfigureIt\Exception\ValidationException
 * @covers \ItPack\ConfigureIt\ValueObject\ValidationMessage
 */
final class ValidationExceptionTest extends Unit
{
    public function testImplementsContract(): void
    {
        $exception = new ValidationException('boom');

        verify($exception)->instanceOf(ValidationExceptionInterface::class);
    }

    public function testEmptyByDefault(): void
    {
        $exception = new ValidationException('boom');

        verify($exception->getMessages())->equals([]);
        verify($exception->getMessageObjects())->equals([]);
    }

    public function testValidationMessageValueObject(): void
    {
        $message = new ValidationMessage('a.b', 'required');

        verify($message)->instanceOf(MessageInterface::class);
        verify($message->path)->equals('a.b');
        verify($message->message)->equals('required');
        verify($message->toString())->equals('a.b: required');
        verify((string) $message)->equals('a.b: required');
    }

    public function testCarriesStructuredMessages(): void
    {
        $exception = new ValidationException(
            'Configuration validation failed:',
            messages: [
                new ValidationMessage('a.b', 'required'),
                new ValidationMessage('', 'root required'),
            ],
        );

        verify($exception)->instanceOf(ValidationExceptionInterface::class);
        verify($exception->getMessages())->equals(['a.b: required', 'root required']);
        verify(count($exception->getMessageObjects()))->equals(2);
        verify($exception->getMessageObjects()[0]->toString())->equals('a.b: required');
    }
}
