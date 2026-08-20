<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;

/**
 * Decides WHERE a detected magic-string candidate's replacement constant
 * should live, given all the sites it was found at. Deliberately separate
 * from the rewriter — the rewriter doesn't know or care which strategy chose
 * the placement, it just receives a ConstantPlacement and applies it.
 *
 * v1 ships two strategies, both fully automatable without inventing new
 * shared abstractions on the user's behalf:
 *
 *   - SameClassConstantStrategy: every occurrence of this value lives in one
 *     class -> a single `private const` on that class.
 *   - PerClassConstantStrategy: occurrences span multiple classes -> each
 *     class gets its OWN same-named constant, independently. No shared
 *     holder class is created. Sharing a constant across unrelated classes
 *     is an architectural decision (new abstraction, new dependency edge)
 *     that stays a human call — a future SharedConstantStrategy could do
 *     this, but only ever on explicit request, never inferred.
 */
interface ConstantPlacementStrategyInterface
{
    /**
     * @param list<LiteralInterface> $occurrences all occurrences of one literal
     *        value, across however many files/classes.
     * @return list<ConstantPlacementInterface> one or more placements — SameClass
     *         produces exactly one, PerClass produces one per distinct class.
     */
    public function decide(array $occurrences): array;
}
