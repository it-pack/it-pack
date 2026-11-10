<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * @group contract
 */
final class InvalidContextValueExceptionTest extends Unit
{
    public function testImplementsMarkerInterface(): void
    {
        self::assertInstanceOf(InvalidValueExceptionInterface::class, new InvalidContextValueException('x'));
    }

    public function testEmptyKey(): void
    {
        self::assertSame('Context key must not be empty.', InvalidContextValueException::emptyKey()->getMessage());
    }

    public function testKeyTooLong(): void
    {
        $expected = 'Context key must not exceed 64 bytes, got 200.';

        self::assertSame($expected, InvalidContextValueException::keyTooLong(200, 64)->getMessage());
    }

    public function testUnsupportedKeyCharacters(): void
    {
        $expected = 'letters, digits, and the separators';

        self::assertStringContainsString(
            $expected,
            InvalidContextValueException::unsupportedKeyCharacters()->getMessage(),
        );
    }

    public function testUnsupportedValue(): void
    {
        $expected = 'objects and resources are rejected';

        self::assertStringContainsString($expected, InvalidContextValueException::unsupportedValue()->getMessage());
    }

    public function testValueTooLarge(): void
    {
        $expected = 'Context value must not exceed 1024 bytes, got 4000.';

        self::assertSame($expected, InvalidContextValueException::valueTooLarge(4000, 1024)->getMessage());
    }

    public function testValueTooDeep(): void
    {
        $expected = 'Context value nesting must not exceed 8 levels, got 12.';

        self::assertSame($expected, InvalidContextValueException::valueTooDeep(12, 8)->getMessage());
    }

    public function testTooManyEntries(): void
    {
        $expected = 'Context must not exceed 64 entries, got 200.';

        self::assertSame($expected, InvalidContextValueException::tooManyEntries(200, 64)->getMessage());
    }

    public function testCollision(): void
    {
        $expected = 'Context key color already exists and the collision policy is Throw.';

        self::assertSame($expected, InvalidContextValueException::collision('color')->getMessage());
    }

    public function testMissingKey(): void
    {
        $expected = 'Context key color is not present.';

        self::assertSame($expected, InvalidContextValueException::missingKey('color')->getMessage());
    }
}
