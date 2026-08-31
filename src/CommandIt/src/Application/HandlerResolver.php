<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Application\HandlerResolverInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;

/**
 * Default handler resolver.
 *
 * Resolves a handler for a command name by looking the registered factory up in
 * the {@see CommandRegistryInterface} and wrapping it in a {@see LazyHandlerProxy}
 * so construction is deferred until the first `handle()` call.
 *
 * @api
 */
final readonly class HandlerResolver implements HandlerResolverInterface
{
    public function __construct(
        private CommandRegistryInterface $registry,
    ) {
    }

    public function resolve(CommandInterface $command): CommandHandlerInterface
    {
        $factory = $this->registry->handlerFactory($command->name()->toString());

        return new LazyHandlerProxy($factory);
    }
}
