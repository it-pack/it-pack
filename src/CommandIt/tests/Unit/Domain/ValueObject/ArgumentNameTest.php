<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;

/**
 * @covers \ItPack\CommandIt\Domain\ValueObject\ArgumentName
 */
final class ArgumentNameTest extends Unit
{
    public function testValidArgumentNameRoundTrips(): void
    {
        self::assertSame('source-path', ArgumentName::fromString('source-path')->toString());
    }

    public function testEmptyArgumentNameIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ArgumentName::fromString('');
    }

    public function testSpaceIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ArgumentName::fromString('bad name');
    }

    public function testLeadingDigitIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ArgumentName::fromString('1arg');
    }

    public function testReservedCharactersAreRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        ArgumentName::fromString('arg:colon');
    }
}
