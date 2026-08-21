<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

/**
 * Handles exactly one command, identified by the name returned from {@see self::handles()}.
 *
 * Handlers are resolved lazily (see `HandlerResolverInterface` and `LazyHandlerProxy`)
 * so constructing an expensive handler is deferred until it is actually needed.
 *
 * @see https://w3id.org/itpack/runit#Activity A handler performs the activity (work) of an execution.
 *
 * @api
 */
interface CommandHandlerInterface
{
    /**
     * Returns the command name this handler handles (the registry key).
     */
    public function handles(): string;

    public function handle(CommandInterface $command): CommandResultInterface;
}
