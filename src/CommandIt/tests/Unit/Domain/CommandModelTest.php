<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;

/**
 * @covers \ItPack\CommandIt\Domain\Command
 * @covers \ItPack\CommandIt\Domain\Argument
 * @covers \ItPack\CommandIt\Domain\Option
 * @covers \ItPack\CommandIt\Domain\CommandInput
 * @covers \ItPack\CommandIt\Domain\CommandDefinition
 */
final class CommandModelTest extends Unit
{
    public function testCommandExposesImmutableIntent(): void
    {
        $input = new CommandInput(['source' => 'a.txt'], ['force' => true]);
        $command = new Command(CommandName::fromString('cache:clear'), $input, ['meta' => 1]);

        self::assertSame('cache:clear', $command->name()->toString());
        self::assertSame('a.txt', $command->input()->argument('source'));
        self::assertTrue($command->input()->option('force'));
        self::assertSame(['meta' => 1], $command->payload());
    }

    public function testArgumentAndOptionCarryMetadata(): void
    {
        $argument = new Argument(ArgumentName::fromString('source'), true, null, 'Source path', 'string');
        $option = new Option(OptionName::fromString('force'), false, false, 'Force', 'bool');

        self::assertTrue($argument->required());
        self::assertSame('string', $argument->type());
        self::assertSame('argument', $argument->kind()->label());
        self::assertSame('option', $option->kind()->label());
        self::assertFalse($option->defaultValue());
    }

    public function testInputMissingKeyThrows(): void
    {
        $input = new CommandInput();

        $this->expectException(InvalidCommandException::class);
        $input->argument('missing');
    }

    public function testDefinitionComputesSignature(): void
    {
        $definition = new CommandDefinition(
            CommandName::fromString('cache:clear'),
            'Clears the cache.',
            [new Argument(ArgumentName::fromString('source'), true)],
            [new Option(OptionName::fromString('force'))],
        );

        self::assertSame('cache:clear <source> --force', $definition->signature());
        self::assertSame('Clears the cache.', $definition->description());
    }
}
