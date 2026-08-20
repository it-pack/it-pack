<?php

declare(strict_types=1);

namespace ItPack\CommandIt\SDK;

use ItPack\CommandIt\Application\CommandExecutionCoordinator;
use ItPack\CommandIt\Application\CommandExecutor;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Application\HandlerResolver;
use ItPack\CommandIt\Contract\Application\CommandExecutorInterface;
use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Command\CommandBusInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Contract\Port\ClockInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Ergonomic entry point that assembles the application core and wires it to the
 * external-effect ports.
 *
 * The facade holds no global container: it is constructed with the ports a
 * consumer chooses (defaults are supplied by the consumer, e.g. the in-memory or
 * system adapters), and it composes the registry, lazy resolver, lifecycle
 * coordinator, and executor. Register definitions with their handler factories,
 * then dispatch built commands.
 *
 * @api
 */
final class CommandIt
{
    private CommandRegistryInterface $registry;

    private CommandExecutorInterface $executor;

    /**
     * @param list<\ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface> $middlewares
     */
    public function __construct(
        ClockInterface $clock,
        IdentifierGeneratorInterface $ids,
        ExecutionReporterInterface $reporter,
        ExecutionContextProviderInterface $context,
        CommandExecutionStorageInterface $storage,
        CommandRegistryInterface $registry = new CommandRegistry(),
        array $middlewares = [],
    ) {
        $this->registry = $registry;
        $coordinator = new CommandExecutionCoordinator($reporter);
        $resolver = new HandlerResolver($registry);

        $this->executor = new CommandExecutor(
            $registry,
            $resolver,
            $coordinator,
            $context,
            $storage,
            $clock,
            $ids,
            $middlewares,
        );
    }

    /**
     * Binds a definition to a (lazy) handler factory.
     *
     * @param callable(): \ItPack\CommandIt\Contract\Command\CommandHandlerInterface $handlerFactory
     */
    public function register(CommandDefinitionInterface $definition, callable $handlerFactory): void
    {
        $this->registry->register($definition, $handlerFactory);
    }

    public function dispatch(CommandInterface $command): CommandResultInterface
    {
        return $this->executor->dispatch($command);
    }

    public function bus(): CommandBusInterface
    {
        return $this->executor;
    }

    public function registry(): CommandRegistryInterface
    {
        return $this->registry;
    }

    public function executor(): CommandExecutorInterface
    {
        return $this->executor;
    }

    /**
     * @throws HandlerNotFoundException when no command is registered under the name
     */
    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }
}
