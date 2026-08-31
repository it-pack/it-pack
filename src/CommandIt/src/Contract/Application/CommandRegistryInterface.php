<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;

/**
 * Name → definition + handler reference registry.
 *
 * A command is registered once, binding its immutable definition to a handler
 * factory. The factory is a `callable` so the handler is only constructed when
 * actually resolved (see `HandlerResolverInterface`), keeping the registry cheap
 * to populate and safe on hot paths.
 *
 * @api
 */
interface CommandRegistryInterface
{
    /**
     * Registers a command definition together with its (lazy) handler factory.
     *
     * @param callable(): CommandHandlerInterface $handlerFactory
     */
    public function register(CommandDefinitionInterface $definition, callable $handlerFactory): void;

    public function has(string $name): bool;

    /**
     * @throws HandlerNotFoundException when no command is registered under $name
     */
    public function definition(string $name): CommandDefinitionInterface;

    /**
     * @return callable(): CommandHandlerInterface
     *
     * @throws HandlerNotFoundException when no command is registered under $name
     */
    public function handlerFactory(string $name): callable;

    /**
     * Returns every registered definition, for enumeration (e.g. CLI registration).
     *
     * @return list<CommandDefinitionInterface>
     */
    public function definitions(): array;
}
