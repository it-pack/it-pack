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
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\CommandExecutor
 *
 * End-to-end dispatch through the default Application components, using
 * in-memory ports so the lifecycle, reporting, storage, and context behavior
 * can be asserted without external infrastructure.
 */
final class CommandExecutorTest extends Unit
{
    private CommandRegistry $registry;
    private CollectingExecutionReporter $reporter;
    private AmbientContextStorage $context;
    private InMemoryCommandExecutionStorage $storage;
    private CommandExecutor $executor;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->reporter = new CollectingExecutionReporter();
        $this->context = new AmbientContextStorage();
        $this->storage = new InMemoryCommandExecutionStorage();

        $this->executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );
    }

    private function register(string $name, CommandHandlerInterface $handler): void
    {
        $this->registry->register(
            new CommandDefinition(CommandName::fromString($name), handlerKey: $name),
            static fn () => $handler,
        );
    }

    private function command(string $name): Command
    {
        return new Command(CommandName::fromString($name), new CommandInput());
    }

    public function testSuccessfulDispatchProducesSucceededResultWithAttachedExecution(): void
    {
        $this->register('demo:run', new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed('done');
            }
        });

        $result = $this->executor->dispatch($this->command('demo:run'));

        self::assertTrue($result->status()->label() === 'succeeded');
        self::assertSame('done', $result->payload());

        $execution = $result->execution();
        self::assertNotNull($execution);
        self::assertInstanceOf(CommandExecution::class, $execution);
        self::assertSame('succeeded', $execution->status()->label());

        $names = $this->occurrenceNames();
        self::assertContains('command.received.v1', $names);
        self::assertContains('command.handled.v1', $names);

        self::assertNull($this->context->current(), 'ambient context restored after dispatch');
    }

    public function testUnknownCommandIsRejectedBeforeExecution(): void
    {
        $this->expectException(HandlerNotFoundException::class);

        $this->executor->dispatch($this->command('missing'));

        self::assertEmpty($this->reporter->entries(), 'no occurrences emitted for unknown command');
    }

    public function testHandlerFailureRethrowsOriginalExceptionAndRecordsFailure(): void
    {
        $this->register('demo:run', new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                throw new \RuntimeException('boom');
            }
        });

        $thrown = null;

        try {
            $this->executor->dispatch($this->command('demo:run'));
        } catch (\RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('boom', $thrown->getMessage(), 'original business exception preserved');

        $names = $this->occurrenceNames();
        self::assertContains('command.received.v1', $names);
        self::assertContains('command.failed.v1', $names);
        self::assertNotContains('command.handled.v1', $names);

        self::assertNull($this->context->current(), 'ambient context restored even on failure');
    }

    public function testMiddlewareRunsAroundHandler(): void
    {
        $middleware = new class () implements CommandMiddlewareInterface {
            public function process(CommandInterface $command, callable $next): CommandResultInterface
            {
                $result = $next($command);
                $payload = $result->payload();
                $value = \is_string($payload) ? $payload : '';

                return CommandResult::succeed($value . '+');
            }
        };

        $this->register('demo:run', new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed('ok');
            }
        });

        $executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
            [$middleware],
        );

        $result = $executor->dispatch($this->command('demo:run'));

        self::assertSame('ok+', $result->payload());
    }

    /**
     * @return list<string>
     */
    private function occurrenceNames(): array
    {
        $names = [];
        foreach ($this->reporter->entries() as $entry) {
            if ($entry['type'] === 'occurrence') {
                $name = $entry['name'];
                if (\is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }
}
