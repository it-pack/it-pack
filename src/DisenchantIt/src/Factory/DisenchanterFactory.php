<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\ClosedSetDetector;
use ItPack\DisenchantIt\Adapter\ConstantNameGenerator;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\LineBasedRewriter;
use ItPack\DisenchantIt\Adapter\NullExecutionRecorder;
use ItPack\DisenchantIt\Adapter\NumericNameGenerator;
use ItPack\DisenchantIt\Adapter\SemConvAliasRewriter;
use ItPack\DisenchantIt\Adapter\SemConvCollector;
use ItPack\DisenchantIt\Contract\ClosedSetDetectorInterface;
use ItPack\DisenchantIt\Contract\DisenchanterFactoryInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\RewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

/**
 * Composition root for the detection + placement (+ SemConv) orchestrator.
 * Pulls the scanner/filter/grouper from the DetectorFactory, the name resolver,
 * the promotion-target seam (Phase 2) and closed-set detector, the line-based
 * rewriter, and the SemConv collector + alias rewriter (both built from one
 * SemConvConfig), then assembles the Disenchanter.
 */
final readonly class DisenchanterFactory implements DisenchanterFactoryInterface
{
    public function __construct(
        private readonly DetectorFactory $detectors,
        private readonly PromotionTargetFactory $promotion,
        private readonly RewriterInterface $rewriter,
        private readonly SemConvCollectorInterface $semconvCollector,
        private readonly SemConvAliasRewriterInterface $semconvAliasRewriter,
        private readonly ClosedSetDetectorInterface $closedSetDetector,
    ) {
    }

    public static function create(bool $convertFqcnToClassConst = false): self
    {
        $semconvConfig = new SemConvConfig();

        return new self(
            DetectorFactory::create($convertFqcnToClassConst),
            PromotionTargetFactory::create($convertFqcnToClassConst),
            new LineBasedRewriter(),
            new SemConvCollector($semconvConfig),
            new SemConvAliasRewriter($semconvConfig),
            new ClosedSetDetector(),
        );
    }

    public function build(): DisenchanterInterface
    {
        return new Disenchanter(
            $this->detectors->build(),
            $this->detectors->buildFilter(),
            $this->detectors->buildGrouper(),
            new ConstantNameResolver(
                ConstantNameGenerator::create(),
                NumericNameGenerator::create(),
            ),
            $this->promotion->build(),
            $this->rewriter,
            $this->semconvCollector,
            $this->semconvAliasRewriter,
            new NullExecutionRecorder(),
            $this->closedSetDetector,
        );
    }
}
