<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Input;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;

/**
 * @covers \ItPack\CommandIt\Adapter\Input\ArrayInputAdapter
 */
final class ArrayInputAdapterTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(
            CommandName::fromString('demo:run'),
            arguments: [new Argument(ArgumentName::fromString('count'), required: true, type: 'int')],
            options: [new Option(OptionName::fromString('force'), required: false, type: 'bool')],
        );
    }

    public function testSplitsFlatArrayAndCoerces(): void
    {
        $input = new ArrayInputAdapter()->parse($this->definition(), [
            'count' => '7',
            'force' => 'true',
        ]);

        self::assertSame(['count' => 7], $input->arguments());
        self::assertSame(['force' => true], $input->options());
    }

    public function testBoolOptionAcceptsAliasesCaseInsensitivelyAndIntegers(): void
    {
        foreach ([
            '1' => true,
            'TRUE' => true,
            'Yes' => true,
            'ON' => true,
            '0' => false,
            'FALSE' => false,
            'No' => false,
            'OFF' => false,
        ] as $raw => $expected) {
            $input = new ArrayInputAdapter()->parse($this->definition(), ['count' => 1, 'force' => $raw]);

            self::assertSame($expected, $input->options()['force'], "bool alias '$raw'");
        }

        $intInput = new ArrayInputAdapter()->parse($this->definition(), ['count' => 1, 'force' => 1]);
        self::assertTrue($intInput->options()['force']);

        $zeroInput = new ArrayInputAdapter()->parse($this->definition(), ['count' => 1, 'force' => 0]);
        self::assertFalse($zeroInput->options()['force']);
    }

    public function testUnknownKeyThrows(): void
    {
        $this->expectException(InvalidCommandException::class);

        new ArrayInputAdapter()->parse($this->definition(), ['bogus' => 1]);
    }

    public function testNonScalarArgumentIsRejected(): void
    {
        $this->expectException(InvalidCommandException::class);

        new ArrayInputAdapter()->parse($this->definition(), ['count' => ['not', 'scalar']]);
    }

    public function testNonScalarOptionIsRejected(): void
    {
        $definition = new CommandDefinition(
            CommandName::fromString('demo:run'),
            options: [new Option(OptionName::fromString('note'), required: false, type: 'string')],
        );

        $this->expectException(InvalidCommandException::class);

        new ArrayInputAdapter()->parse($definition, ['count' => 1, 'note' => ['x']]);
    }
}
