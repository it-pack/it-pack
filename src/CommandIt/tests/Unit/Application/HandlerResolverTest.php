<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Application\HandlerResolver;
use ItPack\CommandIt\Application\LazyHandlerProxy;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\HandlerResolver
 */
final class HandlerResolverTest extends Unit
{
    private function command(string $name): Command
    {
        return new Command(CommandName::fromString($name), new CommandInput());
    }

    public function testResolveWrapsFactoryInLazyProxy(): void
    {
        $constructed = 0;
        $registry = new CommandRegistry();
        $registry->register(
            new CommandDefinition(CommandName::fromString('demo:run')),
            static function () use (&$constructed): CommandHandlerInterface {
                ++$constructed;

                return new class () implements CommandHandlerInterface {
                    public function handles(): string
                    {
                        return 'demo:run';
                    }

                    public function handle(CommandInterface $command): CommandResultInterface
                    {
                        return \ItPack\CommandIt\Domain\CommandResult::succeed('ok');
                    }
                };
            },
        );

        $resolver = new HandlerResolver($registry);
        $handler = $resolver->resolve($this->command('demo:run'));

        self::assertInstanceOf(LazyHandlerProxy::class, $handler);
        self::assertSame(0, $constructed, 'handler must not be constructed before first use');

        $handler->handle($this->command('demo:run'));
        self::assertSame(1, $constructed, 'handler must be constructed exactly once');
    }

    public function testResolveUnknownCommandThrows(): void
    {
        $resolver = new HandlerResolver(new CommandRegistry());

        $this->expectException(HandlerNotFoundException::class);
        $resolver->resolve($this->command('missing'));
    }
}
