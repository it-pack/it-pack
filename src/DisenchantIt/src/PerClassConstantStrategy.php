<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

final class PerClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
    ) {
    }

    /**
     * @param list<StringLiteralInterface> $occurrences
     */
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
            $placements[] = $this->placements->build(
                className: $className,
                constantName: $constantName,
                value: $value,
                occurrences: $classOccurrences,
            );
        }

        return $placements;
    }
}
