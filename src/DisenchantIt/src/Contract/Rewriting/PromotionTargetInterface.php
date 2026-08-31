<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Rewriting;

use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifactInterface;

/**
 * Port for deciding HOW a detected group of literals should be promoted, and
 * building the resulting edit set. This is the Phase-2 promotion seam that
 * replaces the older per-class placement strategies: a target either handles a
 * group (`supports`) or does not, and when it does it emits a single
 * PromotionArtifact the LineBasedRewriter can apply.
 *
 * Concrete adapters: ClassConstantTarget (the previous behavior, isolated behind
 * this seam), BackedEnumTarget (closed sets), FlagSetTarget (Phase 3).
 */
interface PromotionTargetInterface
{
    public function supports(GroupedCandidateInterface $group): bool;

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface;
}
