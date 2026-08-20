<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

final class PerClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly NumericNameGeneratorInterface $numericGenerator,
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
        $constantName = $this->nameFor($occurrences);

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

    /**
     * @param list<LiteralInterface> $occurrences
     */
    private function nameFor(array $occurrences): string
    {
        $lit = $occurrences[0];

        if ($lit->kind === LiteralKind::String->value) {
            return $this->generator->generate($lit->value, $lit->role);
        }

        return $this->numericGenerator->generate($lit, $lit->namingContext) ?? $lit->value;
    }
}
