<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Candidate;

use ItPack\DisenchantIt\Adapter\Grouping\CandidateGrouper;
use ItPack\DisenchantIt\Adapter\Grouping\ClosedSetDetector;

/**
 * Port for one detected candidate grouped together with the closed-set metadata
 * a PromotionTarget needs to decide between a plain constant and a richer target
 * (backed enum, flag set, ...). `candidate` is the existing grouped literal set
 * produced by CandidateGrouper; `isClosedSet`/`proposedEnumName` are filled in
 * by ClosedSetDetector.
 */
interface GroupedCandidateInterface
{
    public CandidateInterface $candidate { get; }

    public bool $isClosedSet { get; }

    /** Proposed enum name (derived from the shared naming context), or null. */
    public ?string $proposedEnumName { get; }
}
