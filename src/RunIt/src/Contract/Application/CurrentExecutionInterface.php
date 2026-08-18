<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

/**
 * Ambient access to the current execution scope.
 *
 * RunIt avoids a mutable static facade. Instead, ambient access is provided
 * through an injected object that resolves the current scope from context
 * storage. Consumers that prefer explicit passing keep using the
 * {@see ExecutionScopeInterface} argument directly.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CurrentExecutionInterface
{
    /**
     * Returns the current execution scope, or null when no execution is active.
     */
    public function get(): ?ExecutionScopeInterface;

    /**
     * Returns the current execution scope, throwing when no execution is active.
     *
     * @throws \ItPack\RunIt\Contract\Exception\RunItExceptionInterface when no execution is active
     */
    public function require(): ExecutionScopeInterface;
}
