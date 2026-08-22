<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Application\CommandInputParser;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;

/**
 * @covers \ItPack\CommandIt\Application\CommandInputParser
 */
final class CommandInputParserTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(
            CommandName::fromString('demo:run'),
            arguments: [
                new Argument(ArgumentName::fromString('count'), required: true, type: 'int'),
                new Argument(ArgumentName::fromString('tag'), required: false, defaultValue: 'latest', type: 'string'),
            ],
            options: [
                new Option(OptionName::fromString('force'), required: false, type: 'bool'),
            ],
        );
    }

    public function testCoercesAndResolvesProvidedValues(): void
    {
        $input = new CommandInputParser()->parse(
            $this->definition(),
            ['count' => '42', 'tag' => 'v1'],
            ['force' => 'true'],
        );

        self::assertSame(['count' => 42, 'tag' => 'v1'], $input->arguments());
        self::assertSame(['force' => true], $input->options());
    }

    public function testOptionalArgumentFallsBackToDefault(): void
    {
        $input = new CommandInputParser()->parse($this->definition(), ['count' => 1], []);

        self::assertSame(['count' => 1, 'tag' => 'latest'], $input->arguments());
        self::assertSame([], $input->options());
    }

    public function testMissingRequiredArgumentThrows(): void
    {
        $this->expectException(InvalidCommandException::class);

        new CommandInputParser()->parse($this->definition(), [], []);
    }

    public function testNonScalarArgumentIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        new CommandInputParser()->parse($this->definition(), ['count' => ['not', 'scalar']], []);
    }

    public function testNonScalarStringArgumentIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        new CommandInputParser()->parse($this->definition(), ['count' => 1, 'tag' => ['x']], []);
    }

    public function testNonScalarOptionIsRejected(): void
    {
        $definition = new CommandDefinition(
            CommandName::fromString('demo:run'),
            options: [new Option(OptionName::fromString('note'), required: false, type: 'string')],
        );

        $this->expectException(InvalidCommandException::class);

        new CommandInputParser()->parse($definition, [], ['note' => ['x']]);
    }
}
