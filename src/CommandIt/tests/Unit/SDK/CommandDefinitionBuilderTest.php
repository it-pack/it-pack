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
