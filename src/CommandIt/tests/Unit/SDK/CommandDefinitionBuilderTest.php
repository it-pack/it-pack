<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;

/**
 * @covers \ItPack\CommandIt\SDK\CommandDefinitionBuilder
 */
final class CommandDefinitionBuilderTest extends Unit
{
    private function argument(): Argument
    {
        return new Argument(ArgumentName::fromString('source'), required: true, type: 'string');
    }

    private function option(): Option
    {
        return new Option(OptionName::fromString('force'), required: false, type: 'bool');
    }

    public function testBuildProducesValidatedDefinition(): void
    {
        $definition = new CommandDefinitionBuilder()
            ->name('cache:clear')
            ->description('Clears the cache')
            ->argument($this->argument())
            ->option($this->option())
            ->build()
        ;

        self::assertSame('cache:clear', $definition->name()->toString());
        self::assertSame('Clears the cache', $definition->description());
        self::assertCount(1, $definition->arguments());
        self::assertCount(1, $definition->options());
        self::assertSame('cache:clear <source> --force', $definition->signature());
    }

    public function testBuilderIsImmutable(): void
    {
        $base = new CommandDefinitionBuilder()->name('cache:clear');
        $extended = $base->description('extended');

        self::assertSame('', $base->build()->description(), 'receiver unchanged');
        self::assertSame('extended', $extended->build()->description());

        // Clone semantics hold for every fluent step, not only description().
        $renamed = $base->name('other:run');
        self::assertSame('cache:clear', $base->build()->name()->toString(), 'name() must not mutate the receiver');
        self::assertSame('other:run', $renamed->build()->name()->toString());

        $withArgument = $base->argument($this->argument());
        self::assertCount(0, $base->build()->arguments(), 'argument() must not mutate the receiver');
        self::assertCount(1, $withArgument->build()->arguments());

        $withOption = $base->option($this->option());
        self::assertCount(0, $base->build()->options(), 'option() must not mutate the receiver');
        self::assertCount(1, $withOption->build()->options());
    }

    public function testAccumulatesMultipleArgumentsAndOptionsInOrder(): void
    {
        $definition = new CommandDefinitionBuilder()
            ->name('demo:run')
            ->argument(new Argument(ArgumentName::fromString('first'), required: true, type: 'string'))
            ->argument(new Argument(ArgumentName::fromString('second'), required: false, type: 'string'))
            ->option(new Option(OptionName::fromString('force'), required: false, type: 'bool'))
            ->option(new Option(OptionName::fromString('mode'), required: false, type: 'string'))
            ->build()
        ;

        self::assertSame(
            ['first', 'second'],
            array_map(static fn ($spec) => $spec->name()->toString(), $definition->arguments()),
        );
        self::assertSame(
            ['force', 'mode'],
            array_map(static fn ($spec) => $spec->name()->toString(), $definition->options()),
        );
    }

    public function testBuildWithoutNameThrows(): void
    {
        $this->expectException(InvalidCommandException::class);

        new CommandDefinitionBuilder()->build();
    }

    public function testNameAcceptsCommandNameObject(): void
    {
        $definition = new CommandDefinitionBuilder()
            ->name(CommandName::fromString('demo:run'))
            ->build()
        ;

        self::assertSame('demo:run', $definition->name()->toString());
    }
}
