<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\AutoConstantPlacementStrategy;
use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementStrategyFactoryInterface;
use ItPack\DisenchantIt\PerClassConstantStrategy;
use ItPack\DisenchantIt\SameClassConstantStrategy;

final readonly class ConstantPlacementStrategyFactory implements ConstantPlacementStrategyFactoryInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameGeneratorInterface $generator,
    ) {
    }

    public static function create(): self
    {
        return new self(ConstantPlacementFactory::create(), ConstantNameGenerator::create());
    }

    public function buildSameClass(): ConstantPlacementStrategyInterface
    {
        return new SameClassConstantStrategy($this->placements, $this->generator);
    }

    public function buildPerClass(): ConstantPlacementStrategyInterface
    {
        return new PerClassConstantStrategy($this->placements, $this->generator);
    }

    public function buildAuto(): ConstantPlacementStrategyInterface
    {
        return new AutoConstantPlacementStrategy(
            sameClass: $this->buildSameClass(),
            perClass: $this->buildPerClass(),
        );
    }
}
