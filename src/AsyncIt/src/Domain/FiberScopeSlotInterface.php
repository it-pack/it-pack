<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Mutable per-Fiber scope slot surface used by the runtime context storages.
 *
 * Backs {@see FiberScopeSlot} so the mutable per-Fiber scope stack and
 * attached scope mapping remain mockable and interface-driven.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface FiberScopeSlotInterface
{
    /**
     * Returns the LIFO token stack.
     *
     * @return array<int, int>
     */
    public function getStack(): array;

    /**
     * Replaces the LIFO token stack.
     *
     * @param array<int, int> $stack
     */
    public function setStack(array $stack): void;

    /**
     * Returns the attached execution scopes keyed by token.
     *
     * @return array<int, ExecutionScopeInterface>
     */
    public function getScopes(): array;

    /**
     * Replaces the attached execution scopes.
     *
     * @param array<int, ExecutionScopeInterface> $scopes
     */
    public function setScopes(array $scopes): void;
}
