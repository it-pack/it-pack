<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Core;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\ExecutionRecorderInterface;
use ItPack\DisenchantIt\Contract\RewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantResult;
use ItPack\DisenchantIt\ValueObject\DisenchantResultInterface;
use ItPack\DisenchantIt\ValueObject\Execution\DisenchantOccurrence;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCode;

/**
 * Detection and placement orchestrator (the "Detect" and "Decide/Rewrite" steps
 * of the DisenchantIt pipeline) for one PHP source string.
 *
 * Composes the scanner, candidate filter, grouper, name generator, placement
 * strategy, and rewriter behind one seam. `detect()` exposes the grouped, named
 * candidates; `rewrite()` runs the full pipeline and returns rewritten source.
 *
 * Hard rule preserved: this is pure source-text-in, data-structure-out. No file
 * is ever required, included, autoloaded, or executed.
 */
final readonly class Disenchanter implements DisenchanterInterface
{
    public function __construct(
        private StringLiteralDetectorInterface     $detector,
        private CandidateFilterInterface           $filter,
        private CandidateGrouperInterface          $grouper,
        private ConstantNameResolverInterface      $resolver,
        private ConstantPlacementStrategyInterface $placement,
        private RewriterInterface                  $rewriter,
        private SemConvCollectorInterface          $collector,
        private SemConvAliasRewriterInterface      $aliasRewriter,
        private ExecutionRecorderInterface         $recorder,
    ) {
    }

    /**
     * Records an immutable execution artifact through the recorder port. Core
     * never depends on a concrete recorder, so a run is identical with or
     * without an active recorder.
     */
    /**
     * @param array<string, mixed> $payload
     */
    private function record(string $name, array $payload = []): void
    {
        $this->recorder->record(new DisenchantOccurrence($name, $payload));
    }

    /**
     * @return array<string, CandidateInterface> keyed by the grouping key.
     *         For strings the key is the literal value; for numbers it is the
     *         value combined with the naming-context signature (see
     *         CandidateGrouper / the grouping strategies). The same key space
     *         is used by `rewrite()` and `rewriteWithSemConv()`, which both
     *         re-run `detect()` internally.
     */
    public function detect(string $code, ?string $relativePath = null): array
    {
        $literals = $this->detector->scanSource(new SourceCode($code));
        $candidates = $this->filter->filterCandidates($literals, $relativePath);
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

        $this->record('disenchant.it.magic_value.detected.v1', ['count' => count($detected)]);

        return $detected;
    }

    public function rewrite(string $code, int $minOccurrences = 1, ?string $relativePath = null): string
    {
        $candidates = $this->detect($code, $relativePath);

        $placements = [];
        foreach ($candidates as $candidate) {
            if ($candidate->count() < $minOccurrences) {
                continue;
            }

            foreach ($this->placement->decide($candidate->occurrences) as $placement) {
                $placements[] = $placement;
            }
        }

        $this->record('disenchant.it.rewrite.v1', ['count' => count($placements)]);

        return $this->rewriter->rewrite($code, $placements);
    }

    public function rewriteWithSemConv(string $code, int $minOccurrences = 1, ?string $existingSemConvSource = null, ?string $relativePath = null): DisenchantResultInterface
    {
        $candidates = $this->detect($code, $relativePath);
        $this->record('disenchant.it.scan.v1', ['count' => count($candidates)]);

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
        $this->record('disenchant.it.rewrite.v1', ['count' => count($placements)]);

        // Step 4: merge placements into the shared SemConv registry.
        $semconvSource = $this->collector->collect($placements, $existingSemConvSource);
        $this->record('disenchant.it.semconv.collect.v1');

        // Step 5: alias each local constant's value at the registry constant.
        $aliased = $this->aliasRewriter->rewrite($rewritten, $placements);
        $this->record('disenchant.it.semconv.alias.v1');

        $this->record('disenchant.it.snapshot.v1', [
            'rewrittenLines' => substr_count($aliased, "\n") + 1,
            'semconvLines' => substr_count($semconvSource, "\n") + 1,
        ]);

        return new DisenchantResult($aliased, $semconvSource);
    }
}
