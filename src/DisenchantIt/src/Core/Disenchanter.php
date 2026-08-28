<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Core;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\CandidateGrouperInterface;
use ItPack\DisenchantIt\Contract\ClosedSetDetectorInterface;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\ExecutionRecorderInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetInterface;
use ItPack\DisenchantIt\Contract\RewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\VerificationObserverInterface;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantResult;
use ItPack\DisenchantIt\ValueObject\DisenchantResultInterface;
use ItPack\DisenchantIt\ValueObject\Execution\DisenchantOccurrence;
use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\SourceCode;

/**
 * Detection and placement orchestrator (the "Detect" and "Decide/Rewrite" steps
 * of the DisenchantIt pipeline) for one PHP source string.
 *
 * Composes the scanner, candidate filter, grouper, name resolver, the promotion
 * target seam, the rewriter, and the SemConv collect/alias steps behind one seam.
 * `detect()` exposes the grouped, named candidates; `rewrite()` runs the full
 * pipeline and returns rewritten source.
 *
 * Hard rule preserved: this is pure source-text-in, data-structure-out. No file
 * is ever required, included, autoloaded, or executed.
 */
final readonly class Disenchanter implements DisenchanterInterface
{
    public function __construct(
        private StringLiteralDetectorInterface $detector,
        private CandidateFilterInterface $filter,
        private CandidateGrouperInterface $grouper,
        private ConstantNameResolverInterface $resolver,
        private PromotionTargetInterface $promotion,
        private RewriterInterface $rewriter,
        private SemConvCollectorInterface $collector,
        private SemConvAliasRewriterInterface $aliasRewriter,
        private ExecutionRecorderInterface $recorder,
        private ClosedSetDetectorInterface $closedSetDetector,
        private ?VerificationObserverInterface $observer = null,
    ) {
    }

    /**
     * @return array<string, CandidateInterface> keyed by the grouping key.
     *         For strings the key is the literal value; for numbers it is the
     *         value combined with the naming-context signature (see
     *         CandidateGrouper / the grouping strategies). The same key space is
     *         used by `rewrite()` and `rewriteWithSemConv()`, which both re-run
     *         `detect()` internally.
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

    public function rewrite(
        string $code,
        int $minOccurrences = 1,
        ?string $relativePath = null,
        bool $dryRun = false,
    ): string {
        $candidates = $this->detect($code, $relativePath);
        $groups = $this->closedSetDetector->detect($candidates);

        $artifacts = $this->buildArtifacts($groups, $minOccurrences);

        if ($dryRun) {
            $this->record('disenchant.it.dry_run.plan.v1', $this->planPayload($artifacts));

            return $this->rewriter->rewrite($code, $artifacts, true);
        }

        $this->record('disenchant.it.rewrite.v1', ['count' => count($artifacts)]);

        $rewritten = $this->rewriter->rewrite($code, $artifacts);
        $this->invokeObserver($rewritten);

        return $rewritten;
    }

    public function rewriteWithSemConv(
        string $code,
        int $minOccurrences = 1,
        ?string $existingSemConvSource = null,
        ?string $relativePath = null,
    ): DisenchantResultInterface {
        $candidates = $this->detect($code, $relativePath);
        $this->record('disenchant.it.scan.v1', ['count' => count($candidates)]);

        $groups = $this->closedSetDetector->detect($candidates);

        $artifacts = $this->buildArtifacts($groups, $minOccurrences);

        // Step 1-3: promote literals to constants / enums.
        $rewritten = $this->rewriter->rewrite($code, $artifacts);
        $this->record('disenchant.it.rewrite.v1', ['count' => count($artifacts)]);

        // SemConv only applies to Const-target placements; enum targets carry none.
        $placements = $this->placementsFromArtifacts($artifacts);

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

        $this->invokeObserver($aliased);

        return new DisenchantResult($aliased, $semconvSource);
    }

    /**
     * Records an immutable execution artifact through the recorder port. Core
     * never depends on a concrete recorder, so a run is identical with or
     * without an active recorder.
     *
     * @param array<string, mixed> $payload
     */
    private function record(string $name, array $payload = []): void
    {
        $this->recorder->record(new DisenchantOccurrence($name, $payload));
    }

    /**
     * Invokes the optional post-rewrite verification observer (out-of-process
     * `php -l`, etc.) on successfully rewritten source. The observer runs only
     * after a successful rewrite, so a business exception raised earlier in the
     * pipeline propagates unchanged (the original failure is preserved, never
     * replaced by a verification failure).
     */
    private function invokeObserver(string $rewrittenSource): void
    {
        $this->observer?->afterRewrite($rewrittenSource);
    }

    /**
     * Builds the structured plan payload recorded on a dry-run: every
     * promotion artifact's kind, its declaration insertions (class + lines), and
     * its per-occurrence literal → reference replacements.
     *
     * @param list<PromotionArtifactInterface> $artifacts
     * @return array<string, mixed>
     */
    private function planPayload(array $artifacts): array
    {
        $edits = [];
        foreach ($artifacts as $artifact) {
            $declarations = [];
            foreach ($artifact->insertions as $insertion) {
                $declarations[] = [
                    'className' => $insertion->className,
                    'topLevel' => $insertion->topLevel,
                    'lines' => $insertion->lines,
                ];
            }

            $replacements = [];
            foreach ($artifact->replacements as $replacement) {
                $replacements[] = [
                    'line' => $replacement->line,
                    'endLine' => $replacement->endLine,
                    'needle' => $replacement->needle,
                    'replacement' => $replacement->replacement,
                ];
            }

            $edits[] = [
                'kind' => $artifact->kind->value,
                'declarations' => $declarations,
                'replacements' => $replacements,
            ];
        }

        return ['count' => count($artifacts), 'edits' => $edits];
    }

    /**
     * @param array<string, GroupedCandidateInterface> $groups
     * @param int $minOccurrences
     * @return list<PromotionArtifactInterface>
     */
    private function buildArtifacts(array $groups, int $minOccurrences): array
    {
        $artifacts = [];
        foreach ($groups as $group) {
            if ($group->candidate->count() < $minOccurrences) {
                continue;
            }

            if (!$this->promotion->supports($group)) {
                continue;
            }

            $artifacts[] = $this->promotion->build($group);
        }

        return $artifacts;
    }

    /**
     * @param list<PromotionArtifactInterface> $artifacts
     * @return list<ConstantPlacementInterface>
     */
    private function placementsFromArtifacts(array $artifacts): array
    {
        $placements = [];
        foreach ($artifacts as $artifact) {
            foreach ($artifact->placements as $placement) {
                $placements[] = $placement;
            }
        }

        return $placements;
    }
}
