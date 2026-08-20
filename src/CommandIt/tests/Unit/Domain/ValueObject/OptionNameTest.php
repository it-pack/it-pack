<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\ValueObject\OptionName;

/**
 * @covers \ItPack\CommandIt\Domain\ValueObject\OptionName
 */
final class OptionNameTest extends Unit
{
    public function testValidOptionNameRoundTrips(): void
    {
        self::assertSame('verbose', OptionName::fromString('verbose')->toString());
    }

    public function testEmptyOptionNameIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        OptionName::fromString('');
    }

    public function testSpaceIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        OptionName::fromString('bad name');
    }

    public function testLeadingDigitIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        OptionName::fromString('1opt');
    }
}
