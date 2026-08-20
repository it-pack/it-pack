<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\GroupingStrategyInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;

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
