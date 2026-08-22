<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Application\CommandExecutionCoordinator;
use ItPack\CommandIt\Application\CommandExecutor;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Application\HandlerResolver;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\HandlerResolver
 * @covers \ItPack\CommandIt\Application\CommandExecutor
 *
 * Verifies that handler construction is deferred (lazy) and that the factory is
 * never invoked for a command with no registration.
 */
final class LazyHandlerResolutionTest extends Unit
{
    private CommandRegistry $registry;

    private CommandExecutor $executor;

    private int $factoryCalls = 0;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $reporter = new CollectingExecutionReporter();

        $this->executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($reporter),
            new AmbientContextStorage(),
            new InMemoryCommandExecutionStorage(),
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );
    }

    private function registerCountingHandler(): void
    {
        $this->registry->register(
            new CommandDefinition(CommandName::fromString('demo:run')),
            function (): CommandHandlerInterface {
                ++$this->factoryCalls;

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
            },
        );
    }

    public function testFactoryIsInvokedExactlyOnceOnDispatch(): void
    {
        $this->registerCountingHandler();

        $this->executor->dispatch(new Command(CommandName::fromString('demo:run'), new CommandInput()));

        self::assertSame(1, $this->factoryCalls, 'lazy factory constructed on first handle()');
    }

    public function testFactoryIsSkippedForUnregisteredCommand(): void
    {
        $this->registerCountingHandler();

        $thrown = null;
        try {
            $this->executor->dispatch(new Command(CommandName::fromString('missing'), new CommandInput()));
        } catch (HandlerNotFoundException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame(0, $this->factoryCalls, 'factory never constructed for an invalid command');
    }
}
