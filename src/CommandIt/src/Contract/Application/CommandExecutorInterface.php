<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandBusInterface;

/**
 * The application-core orchestrator.
 *
 * Implements the dispatch contract ({@see CommandBusInterface}) and is the
 * seam the SDK assembles: it validates the command, resolves the handler, runs
 * the middleware chain within a fiber-bound context, records the execution, and
 * preserves the original business exception on handler failure.
 *
 * @api
 */
interface CommandExecutorInterface extends CommandBusInterface
{
}
