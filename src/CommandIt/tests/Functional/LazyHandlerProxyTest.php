<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommandIt\Application\LazyHandlerProxy;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\LazyHandlerProxy
 */
final class LazyHandlerProxyTest extends Unit
{
    public function testHandlerIsConstructedOnFirstCallAndExactlyOnce(): void
    {
        $constructed = 0;

        $proxy = new LazyHandlerProxy(static function () use (&$constructed): CommandHandlerInterface {
            ++$constructed;

            return new class () implements CommandHandlerInterface {
                public function handles(): string
                {
                    return 'demo:run';
                }

                public function handle(CommandInterface $command): CommandResultInterface
                {
                    return CommandResult::succeed('ok');
                }
            };
        });

        self::assertSame(0, $constructed, 'factory must not run before first use');

        self::assertSame('demo:run', $proxy->handles());
        self::assertSame(
            'ok',
            $proxy->handle(new Command(CommandName::fromString('demo:run'), new CommandInput()))->payload(),
        );

        self::assertSame(1, $constructed, 'handler must be constructed exactly once');
    }
}
