<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\ByValueAndContextGroupingStrategy;
use ItPack\DisenchantIt\Adapter\ByValueGroupingStrategy;
use ItPack\DisenchantIt\Adapter\CandidateFilter;
use ItPack\DisenchantIt\Adapter\CandidateGrouper;
use ItPack\DisenchantIt\Adapter\Detector;
use ItPack\DisenchantIt\Adapter\ParsedSourceCache;
use ItPack\DisenchantIt\Adapter\RoleClassifier;
use ItPack\DisenchantIt\Adapter\ScopeFilter;
use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\DetectorFactoryInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\TraverserFactoryInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantScope;

/**
 * Composition root for the detection half. Builds the nikic/AST scanner and
 * the candidate filter/grouper it is normally composed with, sharing a single
 * RoleClassifier so the classifier is not instantiated twice.
 */
final readonly class DetectorFactory implements DetectorFactoryInterface
{
    public function __construct(
        private readonly RoleClassifierInterface $classifier,
        private readonly ParserFactoryInterface $parsers,
        private readonly TraverserFactoryInterface $traversers,
        private readonly MagicStringVisitorFactoryInterface $visitorFactory,
        private readonly CandidateFilterInterface $filter,
        private readonly CandidateGrouperInterface $grouper,
    ) {
    }

    public static function create(bool $convertFqcnToClassConst = false): self
    {
        $classifier = new RoleClassifier();
        $scope = DisenchantScope::default()->withConvertFqcnToClassConst($convertFqcnToClassConst);

        return new self(
            $classifier,
            NikicParserFactory::create(),
            NikicTraverserFactory::create(),
            MagicStringVisitorFactory::create($scope->getMaxHeredocBodyChars()),
            new CandidateFilter($classifier, new ScopeFilter($scope)),
            new CandidateGrouper(
                new ByValueGroupingStrategy(),
                new ByValueAndContextGroupingStrategy(),
            ),
        );
    }

    public function build(): StringLiteralDetectorInterface
    {
        return new Detector(
            $this->classifier,
            $this->parsers,
            $this->traversers,
            $this->visitorFactory,
            new ParsedSourceCache(),
        );
    }

    public function buildFilter(): CandidateFilterInterface
    {
        return $this->filter;
    }

    public function buildGrouper(): CandidateGrouperInterface
    {
        return $this->grouper;
    }
}
