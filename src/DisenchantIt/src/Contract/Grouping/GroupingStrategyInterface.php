<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Grouping;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Port for choosing the grouping key of a literal. Strings group by value;
 * numbers group by value AND naming context so equal values with different
 * hints stay in separate promotion groups. Concrete adapters:
 * ByValueGroupingStrategy, ByValueAndContextGroupingStrategy.
 */
interface GroupingStrategyInterface
{
    public function groupKey(LiteralInterface $lit): string;
}
