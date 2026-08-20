<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior;

use Codeception\Test\Unit;
use ItPack\CommandIt\Behavior\Domain\CommandNamedBehavior;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Behavior\Domain\CommandNamedBehavior
 */
final class CommandNamedBehaviorTest extends Unit
{
    public function testIsNamedMatchesCommandName(): void
    {
        $artifact = new class () {
            use CommandNamedBehavior;

            public function commandName(): CommandName
            {
                return CommandName::fromString('cache:clear');
            }
        };

        self::assertTrue($artifact->isNamed(CommandName::fromString('cache:clear')));
        self::assertFalse($artifact->isNamed(CommandName::fromString('config:dump')));
    }
}
