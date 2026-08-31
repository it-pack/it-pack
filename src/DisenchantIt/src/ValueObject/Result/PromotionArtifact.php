<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Result;

use ItPack\DisenchantIt\Adapter\Rewriting\LineBasedRewriter;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\Insertion;
use ItPack\DisenchantIt\ValueObject\Rewrite\Replacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

/**
 * Immutable edit set produced by a PromotionTarget and consumed by
 * LineBasedRewriter. Carries the declaration insertions, the per-line literal →
 * reference replacements, the target kind, and (for `Const` targets) the
 * ConstantPlacements that back the SemConv collect/alias steps.
 */
final readonly class PromotionArtifact implements PromotionArtifactInterface
{
    /**
     * @param list<Insertion> $insertions
     * @param list<Replacement> $replacements
     * @param list<ConstantPlacementInterface> $placements
     */
    public function __construct(
        public array $insertions,
        public array $replacements,
        public TargetKind $kind,
        public array $placements = [],
    ) {
    }
}
