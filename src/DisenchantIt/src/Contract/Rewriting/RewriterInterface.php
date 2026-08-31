<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Rewriting;

use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifactInterface;

/**
 * Port for "apply promotion artifacts to one file's source text".
 *
 * v1 ships LineBasedRewriter: plain line insertion + targeted per-line
 * literal replacement, no AST reprinting, no third-party rewrite engine.
 * Trade-off: it trusts the detector's recorded line numbers to still be
 * valid against the file it's given (true as long as the file hasn't
 * changed between scan and rewrite in the same run, which is how
 * disenchant.php uses it) and it does one literal-substring replacement per
 * recorded occurrence line rather than understanding PHP syntax at all on
 * the write side.
 *
 * A RectorRewriter implementing this same interface is the intended future
 * adapter once that's evaluated: Rector's AST-diff-based printer solves
 * formatting preservation properly instead of by convention, and could
 * replace LineBasedRewriter without any caller-side change. Nothing in
 * PlacementStrategy.php or disenchant.php should ever depend on which
 * implementation of this interface is wired in.
 */
interface RewriterInterface
{
    /**
     * @param list<PromotionArtifactInterface> $items
     *        all promotion artifacts that apply to this file. Implementations
     *        must not mutate $code in a way that invalidates the line numbers
     *        recorded on OTHER items' occurrences — LineBasedRewriter processes
     *        items bottom-up so earlier insertions never shift pending anchors.
     * @param bool $dryRun when true, implementations MUST NOT modify $code; they
     *        return it unchanged so a caller (Disenchanter) can produce a
     *        diff/report through the recorder port without writing any edit.
     */
    public function rewrite(string $code, array $items, bool $dryRun = false): string;
}
