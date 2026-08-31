<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\Grouping\ClosedSetDetector;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameGenerator;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\Naming\NumericNameGenerator;
use ItPack\DisenchantIt\Adapter\Reporting\NullExecutionRecorder;
use ItPack\DisenchantIt\Adapter\Rewriting\LineBasedRewriter;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvAliasRewriter;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector;
use ItPack\DisenchantIt\Contract\DisenchanterFactoryInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\Grouping\ClosedSetDetectorInterface;
use ItPack\DisenchantIt\Contract\Naming\DictionaryNameResolverInterface;
use ItPack\DisenchantIt\Contract\Reporting\ExecutionRecorderInterface;
use ItPack\DisenchantIt\Contract\Reporting\VerificationObserverInterface;
use ItPack\DisenchantIt\Contract\Rewriting\RewriterInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\Contract\SemConv\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConvCollectorInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConvStoreInterface;
use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\Naming\NamePolicy;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;

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
        private DetectorFactory $detectors,
        private PromotionTargetFactory $promotion,
        private RewriterInterface $rewriter,
        private SemConvCollectorInterface $semconvCollector,
        private SemConvAliasRewriterInterface $semconvAliasRewriter,
        private ClosedSetDetectorInterface $closedSetDetector,
        private ?ExecutionRecorderInterface $recorder = null,
        private ?VerificationObserverInterface $observer = null,
    ) {
    }

    public static function create(
        bool $convertFqcnToClassConst = false,
        ?ExecutionRecorderInterface $recorder = null,
        ?NamePolicy $namePolicy = null,
        ?DictionaryNameResolverInterface $dictionary = null,
        ?SemConvStoreInterface $semconvStore = null,
        ?VerificationObserverInterface $observer = null,
    ): self {
        $semconvConfig = new SemConvConfig();

        return new self(
            DetectorFactory::create($convertFqcnToClassConst),
            PromotionTargetFactory::create($convertFqcnToClassConst, $namePolicy, $dictionary),
            new LineBasedRewriter(),
            new SemConvCollector($semconvConfig, $semconvStore),
            new SemConvAliasRewriter($semconvConfig),
            new ClosedSetDetector(),
            $recorder,
            $observer,
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
            $this->recorder ?? new NullExecutionRecorder(),
            $this->closedSetDetector,
            $this->observer,
        );
    }
}
