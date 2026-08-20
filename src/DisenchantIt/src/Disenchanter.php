<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantResult;
use ItPack\DisenchantIt\ValueObject\DisenchantResultInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCode;

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
        private readonly ConstantNameResolverInterface $resolver,
        private readonly ConstantPlacementStrategyInterface $placement,
        private readonly RewriterInterface $rewriter,
        private readonly SemConvCollectorInterface $collector,
        private readonly SemConvAliasRewriterInterface $aliasRewriter,
    ) {
    }

    /**
     * @return array<string, CandidateInterface> keyed by the grouping key.
     *         For strings the key is the literal value; for numbers it is the
     *         value combined with the naming-context signature (see
     *         CandidateGrouper / the grouping strategies). The same key space
     *         is used by `rewrite()` and `rewriteWithSemConv()`, which both
     *         re-run `detect()` internally.
     */
    public function detect(string $code): array
    {
        $literals = $this->detector->scanSource(new SourceCode($code));
        $candidates = $this->filter->filterCandidates($literals);
        $groups = $this->grouper->groupByValue($candidates);

        $detected = [];
        foreach ($groups as $groupKey => $occurrences) {
            /** @var list<LiteralInterface> $occurrences */
            $first = $occurrences[0];
            $detected[$groupKey] = new Candidate(
                $first->value,
                $this->resolver->resolve($first),
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

    public function rewriteWithSemConv(string $code, int $minOccurrences = 1, ?string $existingSemConvSource = null): DisenchantResultInterface
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

        // Step 1-3: promote literals to local constants.
        $rewritten = $this->rewriter->rewrite($code, $placements);

        // Step 4: merge placements into the shared SemConv registry.
        $semconvSource = $this->collector->collect($placements, $existingSemConvSource);

        // Step 5: alias each local constant's value at the registry constant.
        $aliased = $this->aliasRewriter->rewrite($rewritten, $placements);

        return new DisenchantResult($aliased, $semconvSource);
    }
}
