<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Application\MiddlewareChain;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\MiddlewareChain
 */
final class MiddlewareChainTest extends Unit
{
    private function command(): Command
    {
        return new Command(CommandName::fromString('demo:run'), new CommandInput());
    }

    private function terminal(): CommandHandlerInterface
    {
        return new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed('|');
            }
        };
    }

    public function testEmptyChainRunsTerminalHandler(): void
    {
        $result = new MiddlewareChain([])->process($this->command(), $this->terminal());

        self::assertSame('|', $result->payload());
    }

    public function testMiddlewaresRunInExplicitOrderAroundHandler(): void
    {
        $outer = $this->appending('O');
        $inner = $this->appending('I');

        $result = new MiddlewareChain([$outer, $inner])->process($this->command(), $this->terminal());

        self::assertSame('|IO', $result->payload());
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $shortCircuit = new class () implements CommandMiddlewareInterface {
            public function process(CommandInterface $command, callable $next): CommandResultInterface
            {
                return CommandResult::succeed('short');
            }
        };

        $result = new MiddlewareChain([$shortCircuit])->process($this->command(), $this->terminal());

        self::assertSame('short', $result->payload());
    }

    private function appending(string $tag): CommandMiddlewareInterface
    {
        return new class ($tag) implements CommandMiddlewareInterface {
            public function __construct(
                private string $tag,
            ) {
            }

            public function process(CommandInterface $command, callable $next): CommandResultInterface
            {
                $result = $next($command);
                $payload = $result->payload();
                $value = \is_string($payload) ? $payload : '';

                return CommandResult::succeed($value . $this->tag);
            }
        };
    }
}
