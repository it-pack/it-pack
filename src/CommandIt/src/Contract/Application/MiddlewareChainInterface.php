<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ContextEnricherInterface;

/**
 * Composes zero or more middlewares around the terminal handler in explicit order.
 *
 * The first registered middleware is the outermost; the last is closest to the
 * handler. Each middleware receives the command, a `next` callable that runs the
 * rest of the chain, and the active {@see ContextEnricherInterface} so it can
 * attach classified context that flows to reporters.
 *
 * @api
 */
interface MiddlewareChainInterface
{
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler,
        ContextEnricherInterface $context,
    ): CommandResultInterface;
}
