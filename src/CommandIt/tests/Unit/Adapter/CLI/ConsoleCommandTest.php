<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CLI;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\CLI\ConsoleCommandBridge;
use ItPack\CommandIt\Adapter\CLI\ConsoleCommandRegistry;
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
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \ItPack\CommandIt\Adapter\CLI\ConsoleCommandBridge
 * @covers \ItPack\CommandIt\Adapter\CLI\ConsoleCommandRegistry
 */
final class ConsoleCommandTest extends Unit
{
    private CommandRegistry $registry;

    private CommandExecutor $executor;

    private CollectingExecutionReporter $reporter;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->reporter = new CollectingExecutionReporter();
        $this->executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            new AmbientContextStorage(),
            new InMemoryCommandExecutionStorage(),
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );
    }

    private function definition(): CommandDefinition
    {
        return new CommandDefinition(
            CommandName::fromString('demo:run'),
            arguments: [new Argument(ArgumentName::fromString('count'), required: true, type: 'int')],
            options: [new Option(OptionName::fromString('force'), required: false, type: 'bool')],
        );
    }

    /**
     * @param \Closure(CommandInterface): CommandResultInterface $handler
     */
    private function register(\Closure $handler): void
    {
        $this->registry->register($this->definition(), static fn () => new class ($handler) implements CommandHandlerInterface {
            /**
             * @param \Closure(CommandInterface): CommandResultInterface $handler
             */
            public function __construct(
                private \Closure $handler,
            ) {
            }

            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return ($this->handler)($command);
            }
        });
    }

    private function app(): Application
    {
        $app = new Application();
        $app->setAutoExit(false);

        foreach (new ConsoleCommandRegistry($this->registry, $this->executor)->toConsoleCommands() as $command) {
            $app->addCommand($command);
        }

        return $app;
    }

    public function testRegistryEmitsOneConsoleCommandPerDefinition(): void
    {
        $this->register(static fn (): CommandResultInterface => CommandResult::succeed('ok'));
        $commands = new ConsoleCommandRegistry($this->registry, $this->executor)->toConsoleCommands();

        self::assertCount(1, $commands);
        self::assertInstanceOf(ConsoleCommandBridge::class, $commands[0]);
    }

    public function testSuccessfulRunWritesPayloadAndReturnsSuccess(): void
    {
        $this->register(static function (CommandInterface $command): CommandResultInterface {
            return CommandResult::succeed(['count' => $command->input()->argument('count')]);
        });

        $output = new BufferedOutput();
        $code = $this->app()->run(new StringInput('demo:run 7'), $output);

        self::assertSame(0, $code);
        self::assertStringContainsString('{"count":7}', $output->fetch());
    }

    public function testBoolOptionIsCoercedFromFlag(): void
    {
        $this->register(static function (CommandInterface $command): CommandResultInterface {
            return CommandResult::succeed(['force' => $command->input()->option('force')]);
        });

        $output = new BufferedOutput();
        $code = $this->app()->run(new StringInput('demo:run 7 --force'), $output);

        self::assertSame(0, $code);
        self::assertStringContainsString('{"force":true}', $output->fetch());
    }

    public function testHandlerFailureReturnsFailureAndWritesError(): void
    {
        $this->register(static function (): CommandResultInterface {
            throw new \RuntimeException('boom');
        });

        $output = new BufferedOutput();
        $code = $this->app()->run(new StringInput('demo:run 7'), $output);

        self::assertNotSame(0, $code);
        self::assertStringContainsString('boom', $output->fetch());
    }

    public function testMissingRequiredArgumentRejectedByConsole(): void
    {
        $this->register(static fn (): CommandResultInterface => CommandResult::succeed('ok'));

        $output = new BufferedOutput();
        $code = $this->app()->run(new StringInput('demo:run'), $output);

        self::assertNotSame(0, $code);
    }
}
