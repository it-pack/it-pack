<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for a fully-resolved promotion edit set: the declaration source to inject
 * (Insertions) and the per-line literal → reference replacements, tagged with the
 * kind of target emitted (Const / Enum / FlagSet / SharedConst).
 *
 * This is the single shape `LineBasedRewriter` consumes, unifying the
 * previously-`ConstantPlacementInterface`-only rewriter path behind one seam
 * (see docs/ADDITIONAL_FEATURES_ROADMAP.md, Phase 2).
 */
interface PromotionArtifactInterface
{
    /** @var list<InsertionInterface> */
    public array $insertions { get; }

    /** @var list<ReplacementInterface> */
    public array $replacements { get; }

    public TargetKind $kind { get; }

    /**
     * The constant placements that produced this artifact, kept so the SemConv
     * collect + alias steps can still run for `Const` targets. Empty for targets
     * that emit no shared constant (e.g. backed enums in Phase 2).
     *
     * @var list<ConstantPlacementInterface>
     */
    public array $placements { get; }
}
