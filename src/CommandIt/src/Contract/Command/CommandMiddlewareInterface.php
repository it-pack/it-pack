<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

/**
 * Cross-cutting logic applied around command handling.
 *
 * A middleware receives the command and a `next` callable that runs the rest of
 * the chain (remaining middlewares, then the handler). Middlewares compose in
 * explicit order and must be non-intrusive: logging, timing, authorization, and
 * similar concerns live here, not inside handlers.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface CommandMiddlewareInterface
{
    /**
     * Processes the command, optionally delegating to the remainder of the chain.
     *
     * @param callable(CommandInterface): CommandResultInterface $next
     */
    public function process(CommandInterface $command, callable $next): CommandResultInterface;
}
