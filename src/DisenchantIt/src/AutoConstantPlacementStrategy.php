<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Picks SameClass vs PerClass automatically based on whether a value's
 * occurrences span one class or several — this is the only "strategy
 * selection" logic that's safe to automate. Anything that would require
 * inventing a new shared class stays out of scope for v1.
 */
final readonly class AutoConstantPlacementStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private SameClassConstantStrategy $sameClass = new SameClassConstantStrategy(),
        private PerClassConstantStrategy  $perClass = new PerClassConstantStrategy(),
    ) {
    }

    /**
     * @param list<StringLiteralInterface> $occurrences
     */
    public function decide(array $occurrences): array
    {
        $classCount = count(array_unique(array_map(
            static fn (StringLiteralInterface $lit): string => $lit->className,
            $occurrences,
        )));

        return $classCount <= 1
            ? $this->sameClass->decide($occurrences)
            : $this->perClass->decide($occurrences);
    }
}
