<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CLI;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\CLI\ArgvInputParser;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;

/**
 * @covers \ItPack\CommandIt\Adapter\CLI\ArgvInputParser
 */
final class ArgvInputParserTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(
            CommandName::fromString('demo:run'),
            arguments: [
                new Argument(ArgumentName::fromString('count'), required: true, type: 'int'),
                new Argument(ArgumentName::fromString('name'), required: false, type: 'string'),
            ],
            options: [
                new Option(OptionName::fromString('force'), required: false, type: 'bool'),
                new Option(OptionName::fromString('mode'), required: false, type: 'string', defaultValue: 'fast'),
            ],
        );
    }

    public function testParsesPositionalAndOptionsWithCoercion(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['5', '--force', '--mode=slow']);

        self::assertSame(['count' => 5], $input->arguments());
        self::assertSame(['force' => true, 'mode' => 'slow'], $input->options());
    }

    public function testSpaceSeparatedOptionValue(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['5', '--mode', 'slow']);

        self::assertSame(['mode' => 'slow'], $input->options());
    }

    public function testOptionalArgumentOmittedAndOptionDefaultApplied(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['3']);

        self::assertSame(['count' => 3], $input->arguments());
        self::assertSame(['mode' => 'fast'], $input->options());
        self::assertFalse($input->hasOption('force'));
    }

    public function testMissingRequiredArgumentThrows(): void
    {
        $this->expectException(InvalidCommandException::class);

        new ArgvInputParser()->parse($this->definition(), []);
    }

    /**
     * argv tokens are always strings (scalars), so the non-scalar rejection path
     * cannot be reached here; this asserts the scalar coercion still applies.
     */
    public function testNonNumericStringTokenCoercesToScalarInt(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['abc']);

        self::assertSame(['count' => 0], $input->arguments());
    }

    public function testNegativeNumberIsAcceptedAsOptionValue(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['5', '--mode', '-7']);

        self::assertSame(['mode' => '-7'], $input->options());
    }

    public function testEqualsFormAcceptsNegativeNumber(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['5', '--mode=-5']);

        self::assertSame(['mode' => '-5'], $input->options());
    }

    public function testDoubleDashTerminatesOptionParsing(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['5', '--', '--not-an-option']);

        self::assertSame(['count' => 5, 'name' => '--not-an-option'], $input->arguments());
        self::assertFalse($input->hasOption('force'));
    }

    public function testNegativeNumberIsAcceptedAsPositionalArgument(): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), ['-5']);

        self::assertSame(['count' => -5], $input->arguments());
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(InvalidCommandException::class);
        $this->expectExceptionMessage('unknown option "--forc"');

        new ArgvInputParser()->parse($this->definition(), ['5', '--forc']);
    }

    public function testSurplusPositionalThrows(): void
    {
        $this->expectException(InvalidCommandException::class);
        $this->expectExceptionMessage('unexpected argument "extra"');

        new ArgvInputParser()->parse($this->definition(), ['5', 'x', 'extra']);
    }

    public function testShortFlagThrows(): void
    {
        $this->expectException(InvalidCommandException::class);
        $this->expectExceptionMessage('short options are not supported: "-f"');

        new ArgvInputParser()->parse($this->definition(), ['5', '-f']);
    }
}
