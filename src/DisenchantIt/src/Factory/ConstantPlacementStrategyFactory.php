<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\AutoConstantPlacementStrategy;
use ItPack\DisenchantIt\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementStrategyFactoryInterface;
use ItPack\DisenchantIt\PerClassConstantStrategy;
use ItPack\DisenchantIt\SameClassConstantStrategy;

final readonly class ConstantPlacementStrategyFactory implements ConstantPlacementStrategyFactoryInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
    ) {
    }

    public static function create(): self
    {
        return new self(ConstantPlacementFactory::create());
    }

    public function buildSameClass(): ConstantPlacementStrategyInterface
    {
        return new SameClassConstantStrategy($this->placements);
    }

    public function buildPerClass(): ConstantPlacementStrategyInterface
    {
        return new PerClassConstantStrategy($this->placements);
    }

    public function buildAuto(): ConstantPlacementStrategyInterface
    {
        return new AutoConstantPlacementStrategy(
            sameClass: $this->buildSameClass(),
            perClass: $this->buildPerClass(),
        );
    }
}
