<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\GroupingStrategyInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

/**
 * Default candidate grouper: dispatches to a per-kind grouping strategy so
 * strings bucket by value while numbers bucket by value + naming context.
 * The returned array is keyed by each strategy's group key.
 */
final readonly class CandidateGrouper implements CandidateGrouperInterface
{
    public function __construct(
        private readonly GroupingStrategyInterface $strings,
        private readonly GroupingStrategyInterface $numbers,
    ) {
    }

    /**
     * @param list<LiteralInterface> $candidates
     * @return array<string, list<LiteralInterface>>
     */
    public function groupByValue(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $lit) {
            $groups[$this->strategyFor($lit)->groupKey($lit)][] = $lit;
        }

        return $groups;
    }

    private function strategyFor(LiteralInterface $lit): GroupingStrategyInterface
    {
        return $lit->kind === LiteralKind::String->value
            ? $this->strings
            : $this->numbers;
    }
}
