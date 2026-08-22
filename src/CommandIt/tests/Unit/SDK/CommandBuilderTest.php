<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\SDK\CommandBuilder;

/**
 * @covers \ItPack\CommandIt\SDK\CommandBuilder
 */
final class CommandBuilderTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(CommandName::fromString('demo:run'));
    }

    public function testBuildProducesCommandWithNameFromDefinition(): void
    {
        $command = new CommandBuilder()
            ->definition($this->definition())
            ->input(new CommandInput(['x' => 1]))
            ->payload('extra')
            ->build()
        ;

        self::assertSame('demo:run', $command->name()->toString());
        self::assertSame(['x' => 1], $command->input()->arguments());
        self::assertSame('extra', $command->payload());
    }

    public function testBuildWithoutDefinitionThrows(): void
    {
        $this->expectException(InvalidCommandException::class);

        new CommandBuilder()->build();
    }
}
