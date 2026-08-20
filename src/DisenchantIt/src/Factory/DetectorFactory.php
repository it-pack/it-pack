<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\CandidateFilter;
use ItPack\DisenchantIt\CandidateGrouper;
use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\DetectorFactoryInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\TraverserFactoryInterface;
use ItPack\DisenchantIt\Detector;
use ItPack\DisenchantIt\ParsedSourceCache;
use ItPack\DisenchantIt\RoleClassifier;

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

    public static function create(): self
    {
        $classifier = new RoleClassifier();

        return new self(
            $classifier,
            NikicParserFactory::create(),
            NikicTraverserFactory::create(),
            MagicStringVisitorFactory::create(),
            new CandidateFilter($classifier),
            new CandidateGrouper(),
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
