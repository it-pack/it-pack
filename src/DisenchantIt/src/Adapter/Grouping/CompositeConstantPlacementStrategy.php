<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Grouping;

use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Factory\ConstantPlacementStrategyFactory;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Composes an ordered set of placement strategies and delegates each candidate
 * to the first strategy that `supports()` its distinct-class count. Adding a new
 * placement strategy is then an open/closed change: write a new strategy
 * implementing ConstantPlacementStrategyInterface and register it with this
 * composite — no edit to the composer (AutoConstantPlacementStrategy) is
 * needed.
 *
 * The default wiring (see ConstantPlacementStrategyFactory::buildAuto) registers
 * SameClass (1 class) then PerClass (>1 class); the composite falls back to the
 * first strategy when none explicitly supports the count.
 *
 * @see ConstantPlacementStrategyInterface
 */
final readonly class CompositeConstantPlacementStrategy implements ConstantPlacementStrategyInterface
{
    /**
     * @param iterable<ConstantPlacementStrategyInterface> $strategies in priority order
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {
    }

    public function supports(int $distinctClassCount): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($distinctClassCount)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<LiteralInterface> $occurrences
     * @return list<\ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacementInterface>
     */
    public function decide(array $occurrences): array
    {
        $classCount = count(array_unique(array_map(
            static fn (LiteralInterface $lit): string => $lit->className,
            $occurrences,
        )));

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($classCount)) {
                return $strategy->decide($occurrences);
            }
        }

        return [];
    }
}
