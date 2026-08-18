<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

final class SameClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameGeneratorInterface $generator,
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

        $classNames = array_unique(array_map(
            static fn (StringLiteralInterface $lit): string => $lit->className,
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
            constantName: $this->generator->generate($value, $occurrences[0]->role),
            value: $value,
            occurrences: $occurrences,
        )];
    }
}
