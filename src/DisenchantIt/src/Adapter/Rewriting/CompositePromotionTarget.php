<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Rewriting;

use ItPack\DisenchantIt\Contract\Rewriting\PromotionTargetInterface;
use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

/**
 * Composes an ordered set of promotion targets and delegates each group to the
 * first target whose `supports()` returns true. Adding a new promotion target
 * (flag set, shared const) is an open/closed change: implement the port and
 * register it here — no edit to the consumers (Disenchanter).
 *
 * Default ordering prefers BackedEnumTarget (closed sets) ahead of
 * ClassConstantTarget (the general fallback).
 */
final readonly class CompositePromotionTarget implements PromotionTargetInterface
{
    /**
     * @param iterable<PromotionTargetInterface> $targets in priority order
     */
    public function __construct(
        private readonly iterable $targets,
    ) {
    }

    public function supports(GroupedCandidateInterface $group): bool
    {
        foreach ($this->targets as $target) {
            if ($target->supports($group)) {
                return true;
            }
        }

        return false;
    }

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface
    {
        foreach ($this->targets as $target) {
            if ($target->supports($group)) {
                return $target->build($group);
            }
        }

        return new PromotionArtifact([], [], TargetKind::Const);
    }
}
