<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Domain\FiberScopeSlot;

/**
 * Creates the mutable per-Fiber scope slot used by the runtime context storages.
 *
 * Lives in the neutral Domain layer (alongside {@see FiberScopeSlot}) so both
 * the Application (factory) and Adapter (storage) layers may depend on it
 * without violating DepTrac's direction rules. The concrete builder lives in
 * `Application\Factory`.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface FiberScopeSlotFactoryInterface
{
    public function create(): FiberScopeSlot;
}
