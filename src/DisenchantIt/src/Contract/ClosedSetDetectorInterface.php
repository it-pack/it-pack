<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;

/**
 * Port for marking detected candidates that form a closed set — a small, stable
 * group of same-kind literals that all appear in the same variable / parameter /
 * array-key context — and proposing an enum name for them.
 *
 * Concrete adapter: ClosedSetDetector.
 */
interface ClosedSetDetectorInterface
{
    /**
     * @param array<string, CandidateInterface> $candidates keyed by grouping key.
     * @return array<string, GroupedCandidateInterface> the same keys, each wrapped
     *         with its closed-set verdict and a proposed enum name (null when not
     *         a closed set).
     */
    public function detect(array $candidates): array;
}
