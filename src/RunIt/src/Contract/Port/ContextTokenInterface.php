<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Opaque token identifying one attached execution scope.
 *
 * A token is returned by {@see ContextStorageInterface::attach()} and accepted by
 * {@see ContextStorageInterface::detach()}. It carries no behavior: equality is
 * the only operation that matters, and the storage uses it to enforce
 * last-in-first-out detachment of the correct scope.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ContextTokenInterface
{
    /**
     * Stable integer identity of the attached scope, used by storages to
     * enforce last-in-first-out detachment of the correct scope.
     */
    public function id(): int;
}
