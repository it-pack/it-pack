<?php

declare(strict_types=1);

namespace Benchmarks;

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
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Hot-path benchmarks for command dispatch and lazy handler resolution.
 */
#[BeforeMethods('setUp')]
final class CommandDispatchBench
{
    private CommandExecutor $executor;

    private HandlerResolver $resolver;

    private CommandRegistry $registry;

    private Command $command;

    public function setUp(): void
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

        $this->resolver = new HandlerResolver($this->registry);

        $this->registry->register(
            new CommandDefinition(CommandName::fromString('bench:run'), handlerKey: 'bench'),
            static fn () => new class () implements CommandHandlerInterface {
                public function handles(): string
                {
                    return 'bench:run';
                }

                public function handle(CommandInterface $command): CommandResultInterface
                {
                    return CommandResult::succeed('ok');
                }
            },
        );

        $this->command = new Command(CommandName::fromString('bench:run'), new CommandInput());
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchDispatch(): void
    {
        $this->executor->dispatch($this->command);
    }

    #[Revs(1000)]
    #[Iterations(5)]
    public function benchHandlerResolution(): void
    {
        $handler = $this->resolver->resolve($this->command);
        $handler->handles();
    }
}
