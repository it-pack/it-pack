<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;

/**
 * Resolves the handler that should run a command.
 *
 * Resolution is lazy by default: the returned handler defers its own
 * construction until first use, so expensive handlers are never built for
 * rejected or invalid commands. A handler that cannot be resolved indicates a
 * missing registration and surfaces as a {@see \ItPack\CommandIt\Contract\Exception\HandlerNotFoundException}.
 *
 * @api
 */
interface HandlerResolverInterface
{
    /**
     * @throws \ItPack\CommandIt\Contract\Exception\HandlerNotFoundException when no handler is registered for the command
     */
    public function resolve(CommandInterface $command): CommandHandlerInterface;
}
