<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

final class SameClassConstantStrategy implements ConstantPlacementStrategyInterface
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

        $classNames = array_unique(array_map(
            static fn (LiteralInterface $lit): string => $lit->className,
            $occurrences,
        ));

        if (count($classNames) !== 1) {
            // Not this strategy's job — caller should have routed this
            // group to PerClassConstantStrategy instead.
            return [];
        }

        $value = $occurrences[0]->value;

        return [$this->placements->build(
            className: $classNames[0],
            constantName: $this->nameFor($occurrences),
            value: $value,
            occurrences: $occurrences,
        )];
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
