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
 * Detection + placement orchestrator (the "Detect" and "Decide/Rewrite" steps
 * of the DisenchantIt pipeline) for one PHP source string.
 *
 * Composes the scanner, candidate filter, grouper, name generator, placement
 * strategy and rewriter behind one seam. `detect()` exposes the grouped, named
 * candidates; `rewrite()` runs the full pipeline and returns rewritten source.
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
        private readonly ConstantPlacementStrategyInterface $placement,
        private readonly RewriterInterface $rewriter,
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
            $first = $occurrences[0] ?? null;
            $detected[$value] = new Candidate(
                $value,
                $this->generator->generate($value, $first?->role),
                $occurrences,
            );
        }

        return $detected;
    }

    public function rewrite(string $code, int $minOccurrences = 1): string
    {
        $candidates = $this->detect($code);

        $placements = [];
        foreach ($candidates as $candidate) {
            if ($candidate->count() < $minOccurrences) {
                continue;
            }

            foreach ($this->placement->decide($candidate->occurrences) as $placement) {
                $placements[] = $placement;
            }
        }

        return $this->rewriter->rewrite($code, $placements);
    }
}
