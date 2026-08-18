<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\DisenchanterFactoryInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Disenchanter;
use ItPack\DisenchantIt\LineBasedRewriter;
use ItPack\DisenchantIt\RewriterInterface;
use ItPack\DisenchantIt\SemConvAliasRewriter;
use ItPack\DisenchantIt\SemConvCollector;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

/**
 * Composition root for the detection + placement (+ SemConv) orchestrator.
 * Pulls the scanner/filter/grouper from the DetectorFactory, the name generator
 * and the (auto-routing) placement strategy from the
 * ConstantPlacementStrategyFactory, the line-based rewriter, and the SemConv
 * collector + alias rewriter (both built from one SemConvConfig), then assembles
 * the Disenchanter.
 */
final readonly class DisenchanterFactory implements DisenchanterFactoryInterface
{
    public function __construct(
        private readonly DetectorFactory $detectors,
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly ConstantPlacementStrategyFactory $placements,
        private readonly RewriterInterface $rewriter,
        private readonly SemConvCollectorInterface $semconvCollector,
        private readonly SemConvAliasRewriterInterface $semconvAliasRewriter,
    ) {
    }

    public static function create(): self
    {
        $semconvConfig = new SemConvConfig();

        return new self(
            DetectorFactory::create(),
            ConstantNameGenerator::create(),
            ConstantPlacementStrategyFactory::create(),
            new LineBasedRewriter(),
            new SemConvCollector($semconvConfig),
            new SemConvAliasRewriter($semconvConfig),
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
            $this->semconvCollector,
            $this->semconvAliasRewriter,
        );
    }
}
