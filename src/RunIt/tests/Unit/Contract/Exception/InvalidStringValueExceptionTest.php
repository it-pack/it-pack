<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * @group contract
 */
final class InvalidStringValueExceptionTest extends Unit
{
    public function testImplementsMarkerInterface(): void
    {
        self::assertInstanceOf(InvalidValueExceptionInterface::class, new InvalidStringValueException('x'));
    }

    public function testEmptyValue(): void
    {
        $exception = InvalidStringValueException::emptyValue();

        self::assertSame('String value must not be empty.', $exception->getMessage());
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function testTooLong(): void
    {
        $exception = InvalidStringValueException::tooLong(200, 128);

        self::assertSame('String value must not exceed 128 bytes, got 200.', $exception->getMessage());
    }

    public function testUnsupportedCharacters(): void
    {
        $exception = InvalidStringValueException::unsupportedCharacters();
        $expected  = 'whitespace, control characters, and other symbols are rejected';

        self::assertStringContainsString($expected, $exception->getMessage());
    }

    public function testMalformedOccurrenceType(): void
    {
        $exception = InvalidStringValueException::malformedOccurrenceType();

        self::assertStringContainsString('.v1', $exception->getMessage());
    }

    public function testNegativeValue(): void
    {
        $exception = InvalidStringValueException::negativeValue('duration nanoseconds');

        self::assertSame('Value for duration nanoseconds must not be negative.', $exception->getMessage());
    }
}
