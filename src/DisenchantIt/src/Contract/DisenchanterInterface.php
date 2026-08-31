<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\Candidate\Candidate;
use ItPack\DisenchantIt\ValueObject\Candidate\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\Result\DisenchantResultInterface;

/**
 * Port for the detection + placement orchestration over one PHP source string.
 * The concrete orchestrator (root Disenchanter) composes the scanner, filter,
 * grouper, name generator, placement strategy and rewriter behind this seam.
 *
 * Every step is pure source-text-in / data-structure-out (or source-text-out):
 * no file is ever required, included, autoloaded or executed.
 */
interface DisenchanterInterface
{
    /**
     * Detect, group, and name the magic-value candidates in $code.
     *
     * @param string|null $relativePath optional relative path of the source's
     *        file; when supplied, the scope filter (configured on the
     *        candidate filter) can apply file-level include/exclude rules.
     *
     * @return array<string, CandidateInterface> keyed by literal value.
     */
    public function detect(string $code, ?string $relativePath = null): array;

    /**
     * Full pipeline for one source string: detect -> decide placements for
     * every candidate whose occurrence count meets $minOccurrences -> apply
     * those placements and return the rewritten source.
     *
     * When $dryRun is true, no edit is applied (the rewriter is told to skip
     * writing) and the planned edits are recorded through the recorder port as a
     * `disenchant.it.dry_run.plan.v1` occurrence so a diff/report can be built
     * without mutating the source.
     */
    public function rewrite(
        string $code,
        int $minOccurrences = 1,
        ?string $relativePath = null,
        bool $dryRun = false,
    ): string;

    /**
     * Full pipeline including the SemConv collect + alias steps: detect ->
     * decide placements -> rewrite -> collect into a shared SemConv registry ->
     * alias each class constant to that registry. Returns the rewritten/aliased
     * source plus the generated SemConv registry source (merged with
     * $existingSemConvSource when supplied, so re-runs accumulate rather than
     * overwrite).
     *
     * @param string $code
     * @param int $minOccurrences
     * @param string|null $existingSemConvSource
     * @param string|null $relativePath
     * @return DisenchantResultInterface
     */
    public function rewriteWithSemConv(
        string $code,
        int $minOccurrences = 1,
        ?string $existingSemConvSource = null,
        ?string $relativePath = null,
    ): DisenchantResultInterface;
}
