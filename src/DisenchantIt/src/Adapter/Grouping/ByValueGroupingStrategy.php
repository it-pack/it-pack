<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Grouping;

use ItPack\DisenchantIt\Contract\Grouping\GroupingStrategyInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * String grouping: bucket by the literal value. Repetition of the same string
 * is the strongest "this is secretly an enum member" signal.
 */
final readonly class ByValueGroupingStrategy implements GroupingStrategyInterface
{
    public function groupKey(LiteralInterface $lit): string
    {
        return $lit->value;
    }
}
