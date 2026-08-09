<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ConstantPlacementStrategyInterface;

/**
 * Port for assembling the placement strategies. Concrete builder lives in the
 * Factory namespace; it is the composition root that injects the
 * ConstantPlacementFactory into each strategy so no strategy `new`s its own
 * collaborator.
 */
interface ConstantPlacementStrategyFactoryInterface
{
    public function buildSameClass(): ConstantPlacementStrategyInterface;

    public function buildPerClass(): ConstantPlacementStrategyInterface;

    public function buildAuto(): ConstantPlacementStrategyInterface;
}
