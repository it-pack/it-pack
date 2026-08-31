<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\Grouping\CompositeConstantPlacementStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\PerClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\SameClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameGenerator;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\Naming\NumericNameGenerator;
use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementStrategyFactoryInterface;
use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Contract\Naming\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\Naming\NumericNameGeneratorInterface;

final readonly class ConstantPlacementStrategyFactory implements ConstantPlacementStrategyFactoryInterface
{
    public function __construct(
        private ConstantPlacementFactoryInterface $placements,
        private ConstantNameGeneratorInterface    $generator,
        private NumericNameGeneratorInterface     $numericGenerator,
    ) {
    }

    public static function create(): self
    {
        return new self(
            ConstantPlacementFactory::create(),
            ConstantNameGenerator::create(),
            NumericNameGenerator::create(),
        );
    }

    public function buildSameClass(): ConstantPlacementStrategyInterface
    {
        return new SameClassConstantStrategy(
            $this->placements,
            new ConstantNameResolver($this->generator, $this->numericGenerator),
        );
    }

    public function buildPerClass(): ConstantPlacementStrategyInterface
    {
        return new PerClassConstantStrategy(
            $this->placements,
            new ConstantNameResolver($this->generator, $this->numericGenerator),
        );
    }

    public function buildAuto(): ConstantPlacementStrategyInterface
    {
        return new CompositeConstantPlacementStrategy([
            $this->buildSameClass(),
            $this->buildPerClass(),
        ]);
    }
}
