<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Default candidate grouper: buckets literals by their value so repetition
 * (the strongest "this is secretly an enum member" signal) is visible before
 * deciding what to name/generate.
 */
final readonly class CandidateGrouper implements CandidateGrouperInterface
{
    /**
     * @param list<StringLiteralInterface> $candidates
     * @return array<string, list<StringLiteralInterface>>
     */
    public function groupByValue(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $literal) {
            $groups[$literal->value][] = $literal;
        }

        return $groups;
    }
}
