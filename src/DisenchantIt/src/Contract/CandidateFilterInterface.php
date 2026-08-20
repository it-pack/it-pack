<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;

/**
 * Port for deciding whether a detected literal is worth promoting to a constant.
 * Concrete adapter: CandidateFilter.
 */
interface CandidateFilterInterface
{
    /**
     * @param list<LiteralInterface> $literals
     * @return list<LiteralInterface>
     */
    public function filterCandidates(array $literals): array;
}
