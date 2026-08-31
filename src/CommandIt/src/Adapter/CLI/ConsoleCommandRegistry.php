<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\CLI;

use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Command\CommandBusInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Enumerates every registered CommandIt command as a Symfony Console command.
 *
 * Each {@see \ItPack\CommandIt\Contract\Command\CommandDefinitionInterface} is
 * projected onto a {@see ConsoleCommandBridge}; the bridge owns dispatch through
 * the shared {@see CommandBusInterface}.
 *
 * @api
 */
final readonly class ConsoleCommandRegistry
{
    public function __construct(
        private CommandRegistryInterface $registry,
        private CommandBusInterface      $bus,
    ) {
    }

    /**
     * @return list<SymfonyCommand>
     */
    public function toConsoleCommands(): array
    {
        return \array_map(
            fn ($definition) => new ConsoleCommandBridge($definition, $this->bus),
            $this->registry->definitions(),
        );
    }
}
