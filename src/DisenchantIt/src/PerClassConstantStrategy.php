<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;

final class PerClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameGeneratorInterface $generator,
    ) {
    }

    /**
     * @param list<LiteralInterface> $occurrences
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
        $constantName = $this->generator->generate($value, $occurrences[0]->role);

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
