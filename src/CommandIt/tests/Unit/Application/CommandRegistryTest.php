<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\CommandRegistry
 */
final class CommandRegistryTest extends Unit
{
    public function testRegisterThenLookup(): void
    {
        $registry = new CommandRegistry();
        $definition = new CommandDefinition(CommandName::fromString('demo:run'), handlerKey: 'demo');

        $registry->register($definition, static fn () => throw new \LogicException('not constructed'));

        self::assertTrue($registry->has('demo:run'));
        self::assertFalse($registry->has('missing'));
        self::assertSame($definition, $registry->definition('demo:run'));

        $factory = $registry->handlerFactory('demo:run');
        self::assertInstanceOf(\Closure::class, $factory);
    }

    public function testMissingDefinitionThrows(): void
    {
        $registry = new CommandRegistry();

        $this->expectException(HandlerNotFoundException::class);
        $registry->definition('missing');
    }

    public function testMissingFactoryThrows(): void
    {
        $registry = new CommandRegistry();

        $this->expectException(HandlerNotFoundException::class);
        $registry->handlerFactory('missing');
    }
}
