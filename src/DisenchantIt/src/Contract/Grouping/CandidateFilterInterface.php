<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Grouping;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Port for deciding whether a detected literal is worth promoting to a constant.
 * Concrete adapter: CandidateFilter.
 */
interface CandidateFilterInterface
{
    /**
     * @param list<LiteralInterface> $literals
     * @param string|null $relativePath relative path of the source file, used by
     *        the scope filter to apply file-level include/exclude rules; omit
     *        (or pass null) when no path is available, in which case only
     *        literal-value rules apply.
     * @return list<LiteralInterface>
     */
    public function filterCandidates(array $literals, ?string $relativePath = null): array;
}
