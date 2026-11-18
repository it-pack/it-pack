<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Application\ActivityScopeInterface;

/**
 * Runtime-local activity scope storage, independent of execution context storage.
 *
 * Storage does not authenticate scopes; consumers must verify their ownership and
 * provenance before using them. New runtimes require explicit propagation.
 *
 * @api
 */
interface ActivityContextStorageInterface
{
    /** Returns the current activity scope, if one is attached. */
    public function current(): ?ActivityScopeInterface;

    /** Attaches a scope and returns its exact detachment token. */
    public function attach(ActivityScopeInterface $scope): ContextTokenInterface;

    /** Detaches the most recently attached scope using its exact token. */
    public function detach(ContextTokenInterface $token): void;
}
