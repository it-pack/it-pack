<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;

/**
 * @group domain
 */
final class InvalidIdentifierExceptionTest extends Unit
{
    public function testImplementsMarkerInterface(): void
    {
        self::assertInstanceOf(InvalidValueExceptionInterface::class, new InvalidIdentifierException('x'));
    }

    public function testEmptyValue(): void
    {
        self::assertSame('Identifier must not be empty.', InvalidIdentifierException::emptyValue()->getMessage());
    }

    public function testTooLong(): void
    {
        $expected = 'Identifier must not exceed 128 bytes, got 500.';

        self::assertSame($expected, InvalidIdentifierException::tooLong(500, 128)->getMessage());
    }

    public function testUnsupportedCharacters(): void
    {
        $exception = InvalidIdentifierException::unsupportedCharacters();
        $expected  = 'visible ASCII characters only';

        self::assertStringContainsString($expected, $exception->getMessage());
    }
}
