<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Grouping;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacementInterface;

/**
 * Port for building a ConstantPlacement value object. Concrete builders live in
 * the Factory namespace and are wired in by the composition root, never via
 * inline `new` at a usage site.
 */
interface ConstantPlacementFactoryInterface
{
    /**
     * @param list<LiteralInterface> $occurrences the subset of occurrences this
     *        placement's constant should replace.
     */
    public function build(
        string $className,
        string $constantName,
        string $value,
        string $type,
        array $occurrences,
    ): ConstantPlacementInterface;
}
