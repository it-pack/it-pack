<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\ValueObject\ValidatedString;

/**
 * @covers \ItPack\CommandIt\Domain\ValueObject\ValidatedString
 * @covers \ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior
 */
final class ValidatedStringTest extends Unit
{
    public function testFromStringRoundTripsVerbatim(): void
    {
        $value = ValidatedString::fromString('config.resolve');

        self::assertSame('config.resolve', $value->toString());
        self::assertSame('config.resolve', (string) $value);
    }

    public function testEmptyValueIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ValidatedString::fromString('   ');
    }

    public function testControlCharactersAreRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ValidatedString::fromString("bad\x00name");
    }

    public function testOverlongValueIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ValidatedString::fromString(str_repeat('a', 256));
    }

    public function testEqualsComparesValueAndType(): void
    {
        $a = ValidatedString::fromString('x');
        $b = ValidatedString::fromString('x');
        $c = ValidatedString::fromString('y');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
