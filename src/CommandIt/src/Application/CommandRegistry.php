<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * In-memory name → definition + handler-factory registry.
 *
 * Registering a duplicate command name fails fast with
 * {@see InvalidCommandException::duplicateCommand()} — collisions are never
 * silently overwritten (ADR 0004).
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
        $name = $definition->name()->toString();

        if (\array_key_exists($name, $this->entries)) {
            throw InvalidCommandException::duplicateCommand($name);
        }

        $this->entries[$name] = [
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
