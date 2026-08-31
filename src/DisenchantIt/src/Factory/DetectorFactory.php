<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\Detection\Detector;
use ItPack\DisenchantIt\Adapter\Detection\ParsedSourceCache;
use ItPack\DisenchantIt\Adapter\Detection\RoleClassifier;
use ItPack\DisenchantIt\Adapter\Detection\ScopeFilter;
use ItPack\DisenchantIt\Adapter\Grouping\ByValueAndContextGroupingStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\ByValueGroupingStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\CandidateFilter;
use ItPack\DisenchantIt\Adapter\Grouping\CandidateGrouper;
use ItPack\DisenchantIt\Contract\Detection\DetectorFactoryInterface;
use ItPack\DisenchantIt\Contract\Detection\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\Detection\ParserFactoryInterface;
use ItPack\DisenchantIt\Contract\Detection\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\Detection\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\Detection\TraverserFactoryInterface;
use ItPack\DisenchantIt\Contract\Grouping\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\Grouping\CandidateGrouperInterface;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScope;

/**
 * Composition root for the detection half. Builds the nikic/AST scanner and
 * the candidate filter/grouper it is normally composed with, sharing a single
 * RoleClassifier so the classifier is not instantiated twice.
 */
final readonly class DetectorFactory implements DetectorFactoryInterface
{
    public function __construct(
        private RoleClassifierInterface $classifier,
        private ParserFactoryInterface $parsers,
        private TraverserFactoryInterface $traversers,
        private MagicStringVisitorFactoryInterface $visitorFactory,
        private CandidateFilterInterface $filter,
        private CandidateGrouperInterface $grouper,
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
