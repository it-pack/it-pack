<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;

/**
 * In-memory name → definition + handler-factory registry.
 *
 * @api
 */
final class CommandRegistry implements CommandRegistryInterface
{
    /**
     * @var array<string, array{definition: CommandDefinitionInterface, factory: callable(): CommandHandlerInterface}>
     */
    private array $entries = [];

    /**
     * @param callable(): CommandHandlerInterface $handlerFactory
     */
    public function register(CommandDefinitionInterface $definition, callable $handlerFactory): void
    {
        $this->entries[$definition->name()->toString()] = [
            'definition' => $definition,
            'factory' => $handlerFactory,
        ];
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->entries);
    }

    public function definition(string $name): CommandDefinitionInterface
    {
        return $this->entries[$name]['definition']
            ?? throw HandlerNotFoundException::forCommand($name);
    }

    /**
     * @return callable(): CommandHandlerInterface
     */
    public function handlerFactory(string $name): callable
    {
        return $this->entries[$name]['factory']
            ?? throw HandlerNotFoundException::forCommand($name);
    }

    /**
     * @return list<CommandDefinitionInterface>
     */
    public function definitions(): array
    {
        return \array_values(\array_map(
            static fn (array $entry): CommandDefinitionInterface => $entry['definition'],
            $this->entries,
        ));
    }
}
