<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Domain\ValueObject\CommandName
 */
final class CommandNameTest extends Unit
{
    public function testValidNameRoundTrips(): void
    {
        $name = CommandName::fromString('cache:clear');

        self::assertSame('cache:clear', $name->toString());
    }

    public function testNamespacedNameIsAccepted(): void
    {
        self::assertSame(
            'it-pack.config.resolve',
            CommandName::fromString('it-pack.config.resolve')->toString(),
        );
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        CommandName::fromString('');
    }

    public function testWhitespaceIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        CommandName::fromString('bad name');
    }

    public function testLeadingDigitIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        CommandName::fromString('1cache');
    }

    public function testEqualsIsTypeAware(): void
    {
        $name = CommandName::fromString('cache:clear');

        self::assertTrue($name->equals(CommandName::fromString('cache:clear')));
        self::assertFalse($name->equals(CommandName::fromString('other')));
    }
}
