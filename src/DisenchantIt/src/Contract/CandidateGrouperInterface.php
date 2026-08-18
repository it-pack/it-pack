<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Port for grouping candidates by literal value so repetition is visible before
 * deciding what to name/generate. Concrete adapter: CandidateGrouper.
 */
interface CandidateGrouperInterface
{
    /**
     * @param list<StringLiteralInterface> $candidates
     * @return array<string, list<StringLiteralInterface>>
     */
    public function groupByValue(array $candidates): array;
}
