<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Opaque token identifying one entered command context.
 *
 * A token is returned by {@see ExecutionContextProviderInterface::enter()} and
 * accepted by {@see ExecutionContextProviderInterface::leave()}. It carries no
 * behavior: equality is the only operation that matters, and the storage uses it
 * to enforce last-in-first-out detachment of the correct context.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ContextTokenInterface
{
}
