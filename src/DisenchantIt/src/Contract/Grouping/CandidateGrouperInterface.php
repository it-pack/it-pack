<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Grouping;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Port for grouping candidates by literal value so repetition is visible before
 * deciding what to name/generate. Concrete adapter: CandidateGrouper.
 */
interface CandidateGrouperInterface
{
    /**
     * @param list<LiteralInterface> $candidates
     * @return array<string, list<LiteralInterface>>
     */
    public function groupByValue(array $candidates): array;
}
