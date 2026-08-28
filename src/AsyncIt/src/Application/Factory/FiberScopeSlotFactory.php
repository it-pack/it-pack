<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Domain\FiberScopeSlot;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;

/**
 * Concrete {@see FiberScopeSlotFactoryInterface} building an empty {@see FiberScopeSlot}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberScopeSlotFactory implements FiberScopeSlotFactoryInterface
{
    public function create(): FiberScopeSlot
    {
        return new FiberScopeSlot();
    }
}
