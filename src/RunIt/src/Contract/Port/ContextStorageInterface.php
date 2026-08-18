<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Port that stores the ambient execution scope for the current call stack.
 *
 * Conventional synchronous PHP uses a last-in-first-out stack: attaching a scope
 * returns a token, and detaching with that exact token restores the previous
 * scope. Fiber- and Swoole-aware adapters implement the same contract while
 * keeping the scope isolated to their concurrency primitive.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ContextStorageInterface
{
    /**
     * Returns the scope currently attached to this storage, or null when none.
     */
    public function current(): ?ExecutionScopeInterface;

    /**
     * Attaches a scope and returns a token that must be used to detach it.
     */
    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface;

    /**
     * Detaches the scope represented by the token.
     *
     * The exact token returned by {@see self::attach()} must be supplied; any
     * other token is rejected so a detached or replaced scope is never restored
     * by mistake.
     */
    public function detach(ContextTokenInterface $token): void;
}
