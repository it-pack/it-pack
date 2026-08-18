<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\DisenchanterFactoryInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Disenchanter;
use ItPack\DisenchantIt\LineBasedRewriter;
use ItPack\DisenchantIt\RewriterInterface;

/**
 * Composition root for the detection + placement orchestrator. Pulls the
 * scanner, filter and grouper from the DetectorFactory, the name generator and
 * the (auto-routing) placement strategy from the ConstantPlacementStrategyFactory,
 * and the line-based rewriter, then assembles the Disenchanter.
 */
final readonly class DisenchanterFactory implements DisenchanterFactoryInterface
{
    public function __construct(
        private readonly DetectorFactory $detectors,
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly ConstantPlacementStrategyFactory $placements,
        private readonly RewriterInterface $rewriter,
    ) {
    }

    public static function create(): self
    {
        return new self(
            DetectorFactory::create(),
            ConstantNameGenerator::create(),
            ConstantPlacementStrategyFactory::create(),
            new LineBasedRewriter(),
        );
    }

    public function build(): DisenchanterInterface
    {
        return new Disenchanter(
            $this->detectors->build(),
            $this->detectors->buildFilter(),
            $this->detectors->buildGrouper(),
            $this->generator,
            $this->placements->buildAuto(),
            $this->rewriter,
        );
    }
}
