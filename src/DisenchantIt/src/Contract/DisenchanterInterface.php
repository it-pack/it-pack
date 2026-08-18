<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantResultInterface;

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
     * Detect, group and name the magic-value candidates in $code.
     *
     * @return array<string, CandidateInterface> keyed by literal value.
     */
    public function detect(string $code): array;

    /**
     * Full pipeline for one source string: detect -> decide placements for
     * every candidate whose occurrence count meets $minOccurrences -> apply
     * those placements and return the rewritten source.
     */
    public function rewrite(string $code, int $minOccurrences = 1): string;

    /**
     * Full pipeline including the SemConv collect + alias steps: detect ->
     * decide placements -> rewrite -> collect into a shared SemConv registry ->
     * alias each class constant to that registry. Returns the rewritten/aliased
     * source plus the generated SemConv registry source (merged with
     * $existingSemConvSource when supplied, so re-runs accumulate rather than
     * overwrite).
     *
     * @return DisenchantResultInterface
     */
    public function rewriteWithSemConv(string $code, int $minOccurrences = 1, ?string $existingSemConvSource = null): DisenchantResultInterface;
}
