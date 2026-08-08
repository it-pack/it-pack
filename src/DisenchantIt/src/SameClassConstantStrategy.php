<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

final class SameClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function decide(array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }

        $classNames = array_unique(array_map(
            static fn (StringLiteral $lit): string => $lit->className,
            $occurrences,
        ));

        if (count($classNames) !== 1) {
            // Not this strategy's job — caller should have routed this
            // group to PerClassConstantStrategy instead.
            return [];
        }

        $value = $occurrences[0]->value;

        return [new ConstantPlacement(
            className: $classNames[0],
            constantName: Detector::suggestConstantName($value, $occurrences[0]->role),
            value: $value,
            occurrences: $occurrences,
        )];
    }
}
