<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

/**
 * Dispatches a command and returns its immutable result.
 *
 * The bus is the single entry point consumers use; the orchestration behind it
 * (validation, resolution, lifecycle, reporting) is supplied by the application
 * core (see `CommandExecutorInterface`).
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Command
 */
interface CommandBusInterface
{
    /**
     * Runs the full command lifecycle and returns the result.
     *
     * On a handler failure the original {@see \Throwable} is rethrown; the result
     * is only returned for handled (non-throwing) outcomes.
     */
    public function dispatch(CommandInterface $command): CommandResultInterface;
}
