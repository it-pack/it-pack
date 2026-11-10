<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * @group contract
 */
final class InvalidAttributeExceptionTest extends Unit
{
    public function testImplementsMarkerInterface(): void
    {
        self::assertInstanceOf(InvalidValueExceptionInterface::class, new InvalidAttributeException('x'));
    }

    public function testEmptyKey(): void
    {
        self::assertSame('Attribute key must not be empty.', InvalidAttributeException::emptyKey()->getMessage());
    }

    public function testKeyTooLong(): void
    {
        $expected = 'Attribute key must not exceed 64 bytes, got 200.';

        self::assertSame($expected, InvalidAttributeException::keyTooLong(200, 64)->getMessage());
    }

    public function testUnsupportedKeyCharacters(): void
    {
        $expected = 'letters, digits, and the separators';

        self::assertStringContainsString(
            $expected,
            InvalidAttributeException::unsupportedKeyCharacters()->getMessage(),
        );
    }

    public function testTooManyAttributes(): void
    {
        $expected = 'Attribute count must not exceed 128, got 500.';

        self::assertSame($expected, InvalidAttributeException::tooManyAttributes(500, 128)->getMessage());
    }

    public function testUnsupportedValue(): void
    {
        $expected = 'objects and resources are rejected';

        self::assertStringContainsString($expected, InvalidAttributeException::unsupportedValue()->getMessage());
    }

    public function testValueTooLong(): void
    {
        $expected = 'Attribute value must not exceed 1024 bytes, got 4000.';

        self::assertSame($expected, InvalidAttributeException::valueTooLong(4000, 1024)->getMessage());
    }

    public function testValueTooDeep(): void
    {
        $expected = 'Attribute value nesting must not exceed depth 8, got 12.';

        self::assertSame($expected, InvalidAttributeException::valueTooDeep(12, 8)->getMessage());
    }
}
