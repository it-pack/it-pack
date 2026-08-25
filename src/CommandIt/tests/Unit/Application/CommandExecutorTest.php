<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SafeSerializer;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Application\CommandExecutionCoordinator;
use ItPack\CommandIt\Application\CommandExecutor;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Application\HandlerResolver;
use ItPack\CommandIt\Contract\Application\CommandExecutionCoordinatorInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ContextEnricherInterface;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;
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
            new CommandDefinition(CommandName::fromString($name)),
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
            public function process(CommandInterface $command, callable $next, ContextEnricherInterface $context): CommandResultInterface
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

    public function testResultPayloadIsNormalizedBeforeStorageWhenSerializerProvided(): void
    {
        $executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
            [],
            new SafeSerializer(),
        );

        $this->registry->register(new CommandDefinition(CommandName::fromString('demo:run')), static fn () => new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed(new class () {
                    /** @return array{normalized: bool} */
                    public function toArray(): array
                    {
                        return ['normalized' => true];
                    }
                });
            }
        });

        $result = $executor->dispatch($this->command('demo:run'));

        // The caller receives the original (un-normalized) payload object.
        self::assertIsObject($result->payload());

        // The persisted execution carries the safely-normalized payload.
        $execution = $result->execution();
        self::assertInstanceOf(CommandExecution::class, $execution);
        $stored = $this->storage->find($execution->id());
        self::assertNotNull($stored);
        self::assertInstanceOf(CommandExecution::class, $stored);
        $storedResult = $stored->result();
        self::assertNotNull($storedResult);
        self::assertSame(['normalized' => true], $storedResult->payload());
    }

    public function testSerializationFailureKeepsOriginalPayloadBestEffort(): void
    {
        $executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
            [],
            new SafeSerializer(),
        );

        $this->registry->register(new CommandDefinition(CommandName::fromString('demo:run')), static fn () => new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed(new \stdClass());
            }
        });

        $result = $executor->dispatch($this->command('demo:run'));

        // A serializer rejection must never replace the business result.
        self::assertInstanceOf(\stdClass::class, $result->payload());

        $execution = $result->execution();
        self::assertInstanceOf(CommandExecution::class, $execution);
        $stored = $this->storage->find($execution->id());
        self::assertNotNull($stored);
        self::assertInstanceOf(CommandExecution::class, $stored);
        $storedResult = $stored->result();
        self::assertNotNull($storedResult);
        self::assertInstanceOf(\stdClass::class, $storedResult->payload());
    }

    public function testMiddlewareAttachedSecretIsRedactedByReporter(): void
    {
        $reporter = new SafeReporterFactory()->wrap($this->reporter);

        $executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($reporter, $this->context),
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
            [new class () implements CommandMiddlewareInterface {
                public function process(
                    CommandInterface $command,
                    callable $next,
                    ContextEnricherInterface $context,
                ): CommandResultInterface {
                    $context->enrich(static function (CommandContextInterface $current): CommandContextInterface {
                        return $current
                            ->with('secret', 'topsecret', Classification::Secret)
                            ->with('trace_id', 'abc123', Classification::Public)
                        ;
                    });

                    return $next($command);
                }
            }],
        );

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

        $executor->dispatch($this->command('demo:run'));

        $secret = null;
        $traceId = null;
        $found = false;

        foreach ($this->reporter->entries() as $entry) {
            if (($entry['type'] ?? null) === 'occurrence' && ($entry['name'] ?? null) === 'command.handled.v1') {
                $context = $entry['context'] ?? [];
                self::assertIsArray($context);
                /** @var array<string, mixed> $context */
                $secret = $context['secret'] ?? null;
                $traceId = $context['trace_id'] ?? null;
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'command.handled.v1 occurrence emitted');

        self::assertNotNull($secret, 'secret entry present in reporter context');
        self::assertIsString($secret);
        self::assertNotSame('topsecret', $secret, 'secret never reaches the reporter in plaintext');
        self::assertStringContainsString('redacted', $secret);
        self::assertSame('abc123', $traceId, 'non-secret context entry is preserved');
    }

    public function testCoordinatorFailureDuringFailureRecordingStillRethrowsOriginalException(): void
    {
        $inner = new CommandExecutionCoordinator($this->reporter);

        $coordinator = new class ($inner) implements CommandExecutionCoordinatorInterface {
            public function __construct(
                private readonly CommandExecutionCoordinatorInterface $inner,
            ) {
            }

            public function begin(
                CommandDefinitionInterface $definition,
                CommandExecutionIdInterface $id,
                int $startedAt,
            ): ExecutionArtifactInterface {
                return $this->inner->begin($definition, $id, $startedAt);
            }

            public function succeed(
                ExecutionArtifactInterface $execution,
                CommandResultInterface $result,
                int $endedAt,
                int $durationMs,
            ): ExecutionArtifactInterface {
                return $this->inner->succeed($execution, $result, $endedAt, $durationMs);
            }

            public function fail(
                ExecutionArtifactInterface $execution,
                \Throwable $error,
                int $endedAt,
                int $durationMs,
            ): ExecutionArtifactInterface {
                throw new \LogicException('coordinator could not record the failure');
            }
        };

        $original = new \RuntimeException('boom');
        $this->registerThrowing('demo:run', $original);

        $executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            $coordinator,
            $this->context,
            $this->storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );

        $thrown = null;

        try {
            $executor->dispatch($this->command('demo:run'));
        } catch (\RuntimeException $error) {
            $thrown = $error;
        }

        self::assertSame($original, $thrown, 'the original business exception survives a failing coordinator');
        self::assertContains('command.received.v1', $this->occurrenceNames());
    }

    public function testStorageFailureIsSwallowedOnSuccessDispatch(): void
    {
        $executor = $this->executorWithStorage(new class () implements CommandExecutionStorageInterface {
            public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void
            {
                throw new \RuntimeException('storage down');
            }

            public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface
            {
                return null;
            }
        });

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

        $result = $executor->dispatch($this->command('demo:run'));

        self::assertSame('succeeded', $result->status()->label());
    }

    public function testStorageFailureOnFailurePathPreservesOriginalException(): void
    {
        $executor = $this->executorWithStorage(new class () implements CommandExecutionStorageInterface {
            public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void
            {
                throw new \RuntimeException('storage down');
            }

            public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface
            {
                return null;
            }
        });

        $original = new \RuntimeException('boom');
        $this->registerThrowing('demo:run', $original);

        $thrown = null;

        try {
            $executor->dispatch($this->command('demo:run'));
        } catch (\RuntimeException $error) {
            $thrown = $error;
        }

        self::assertSame($original, $thrown);
    }

    private function registerThrowing(string $name, \RuntimeException $thrown): void
    {
        $this->registry->register(
            new CommandDefinition(CommandName::fromString($name)),
            static fn () => new class ($thrown) implements CommandHandlerInterface {
                public function __construct(
                    private readonly \RuntimeException $thrown,
                ) {
                }

                public function handles(): string
                {
                    return 'demo:run';
                }

                public function handle(CommandInterface $command): CommandResultInterface
                {
                    throw $this->thrown;
                }
            },
        );
    }

    private function executorWithStorage(CommandExecutionStorageInterface $storage): CommandExecutor
    {
        return new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator($this->reporter),
            $this->context,
            $storage,
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );
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
