<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

final class PerClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function decide(array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }

        $byClass = [];
        foreach ($occurrences as $lit) {
            $byClass[$lit->className][] = $lit;
        }

        $value = $occurrences[0]->value;
        $constantName = Detector::suggestConstantName($value, $occurrences[0]->role);

        $placements = [];
        foreach ($byClass as $className => $classOccurrences) {
            $placements[] = new ConstantPlacement(
                className: $className,
                constantName: $constantName,
                value: $value,
                occurrences: $classOccurrences,
            );
        }

        return $placements;
    }
}
