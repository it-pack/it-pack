<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Grouping;

use ItPack\DisenchantIt\Contract\Grouping\GroupingStrategyInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;

/**
 * Numeric grouping: bucket by value AND naming-context signature, with a
 * kind prefix (`i:`/`f:`) so equal numbers never collapse with strings or with
 * each other across different hints (e.g. two `100`s named from distinct
 * variables stay in separate groups).
 */
final readonly class ByValueAndContextGroupingStrategy implements GroupingStrategyInterface
{
    public function groupKey(LiteralInterface $lit): string
    {
        $kind = $lit->kind === LiteralKind::Float->value ? 'f' : 'i';
        $signature = $lit->namingContext?->signature() ?? '';

        return $kind . ':' . $lit->value . '|' . $signature;
    }
}
