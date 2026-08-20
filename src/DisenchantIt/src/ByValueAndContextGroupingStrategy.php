<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\GroupingStrategyInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

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
