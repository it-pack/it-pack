<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\CompositeConstantPlacementStrategy;
use ItPack\DisenchantIt\Adapter\ConstantNameGenerator;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\NumericNameGenerator;
use ItPack\DisenchantIt\Adapter\PerClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\SameClassConstantStrategy;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementStrategyFactoryInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Contract\NumericNameGeneratorInterface;

final readonly class ConstantPlacementStrategyFactory implements ConstantPlacementStrategyFactoryInterface
{
    public function __construct(
        private readonly ConstantPlacementFactoryInterface $placements,
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly NumericNameGeneratorInterface $numericGenerator,
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
