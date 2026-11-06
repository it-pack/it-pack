<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Storage-issued token for one attached execution scope.
 *
 * A token is returned by {@see ContextStorageInterface::attach()} and accepted by
 * {@see ContextStorageInterface::detach()}. The issuing storage determines
 * token ownership and enforces last-in-first-out detachment.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ContextTokenInterface
{
    /**
     * Returns a stable storage-defined integer for this token's lifetime.
     * Values need not uniquely identify tokens or scopes; they may identify
     * the owning Fiber or coroutine. Do not use this value alone to authorize
     * detachment or infer token equality.
     */
    public function id(): int;
}
