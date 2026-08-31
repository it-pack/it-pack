<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Candidate;

/**
 * Default immutable GroupedCandidate: a detected candidate plus its closed-set
 * metadata. Wraps an existing `CandidateInterface` so the promotion seam can
 * carry extra signals without disturbing the grouped-candidate contract.
 */
final readonly class GroupedCandidate implements GroupedCandidateInterface
{
    public function __construct(
        public CandidateInterface $candidate,
        public bool $isClosedSet = false,
        public ?string $proposedEnumName = null,
    ) {
    }
}
