<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Detection orchestrator (the "Detect" step of the DisenchantIt pipeline).
 *
 * Composes the scanner, candidate filter, grouper and name generator behind one
 * seam so a caller gets grouped, named magic-value candidates without caring
 * which adapter implements each responsibility. Rewriting/placement are separate
 * steps that consume the resulting Candidate list.
 *
 * Hard rule preserved: this is pure source-text-in, data-structure-out. No file
 * is ever required, included, autoloaded or executed.
 */
final readonly class Disenchanter implements DisenchanterInterface
{
    public function __construct(
        private readonly StringLiteralDetectorInterface $detector,
        private readonly CandidateFilterInterface $filter,
        private readonly CandidateGrouperInterface $grouper,
        private readonly ConstantNameGeneratorInterface $generator,
    ) {
    }

    /**
     * @return array<string, CandidateInterface> keyed by literal value.
     */
    public function detect(string $code): array
    {
        $literals = $this->detector->scanSource($code);
        $candidates = $this->filter->filterCandidates($literals);
        $groups = $this->grouper->groupByValue($candidates);

        $detected = [];
        foreach ($groups as $value => $occurrences) {
            /** @var list<StringLiteralInterface> $occurrences */
            $detected[$value] = new Candidate(
                $value,
                $this->generator->generate($value),
                $occurrences,
            );
        }

        return $detected;
    }
}
