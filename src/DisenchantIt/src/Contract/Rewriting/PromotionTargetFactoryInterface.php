<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Rewriting;

use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\Factory\PromotionTargetFactory;

/**
 * Port for assembling the composite promotion target used by the Disenchanter.
 * Concrete adapter: PromotionTargetFactory.
 */
interface PromotionTargetFactoryInterface
{
    public function build(): PromotionTargetInterface;
}
