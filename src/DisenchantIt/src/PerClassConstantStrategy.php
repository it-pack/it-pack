<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;

final class PerClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameResolverInterface $resolver,
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
        $constantName = $this->resolver->resolve($occurrences[0]);

        $placements = [];
        foreach ($byClass as $className => $classOccurrences) {
            $placements[] = $this->placements->build(
                className: $className,
                constantName: $constantName,
                value: $value,
                type: $occurrences[0]->kind,
                occurrences: $classOccurrences,
            );
        }

        return $placements;
    }

}
