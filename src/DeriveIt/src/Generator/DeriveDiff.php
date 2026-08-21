<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

/**
 * Result of a dry-run derivation diff (G9 / EC15 / D5) used for CI drift
 * detection: given an existing committed interface source and the freshly
 * derived source, report whether they diverge and a human-readable unified diff.
 *
 * Immutable value object: all state is public readonly.
 */
final class DeriveDiff
{
    public function __construct(
        public readonly bool $drift,
        public readonly string $generated,
        public readonly string $existing,
        public readonly string $diff,
    ) {
    }

    /**
     * Compare the freshly derived source against the existing committed source.
     *
     * Drift is exact-string inequality (derivation is deterministic, so an
     * unchanged target must reproduce the committed source verbatim). The diff
     * is a unified-style line diff of the differing region, empty when there is
     * no drift.
     */
    public static function compute(string $existing, string $generated): self
    {
        $drift = $existing !== $generated;
        $diff = $drift ? self::unified($existing, $generated) : '';

        return new self(drift: $drift, generated: $generated, existing: $existing, diff: $diff);
    }

    /**
     * Compact unified diff (no external dependency so `src/` stays
     * production-clean): strip the shared line prefix and suffix, then emit the
     * remaining region as `-` (removed) / `+` (added) lines under a single
     * `@@` hunk header. Correct for CI drift review; not a minimal LCS diff.
     */
    private static function unified(string $old, string $new): string
    {
        $a = explode("\n", rtrim($old, "\n"));
        $b = explode("\n", rtrim($new, "\n"));
        $n = count($a);
        $m = count($b);

        $start = 0;
        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $start++;
        }

        $endA = $n;
        $endB = $m;
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            $endA--;
            $endB--;
        }

        $removed = array_slice($a, $start, $endA - $start);
        $added = array_slice($b, $start, $endB - $start);

        $out = [sprintf('@@ -%d,%d +%d,%d @@', $start + 1, $endA - $start, $start + 1, $endB - $start)];
        foreach ($removed as $line) {
            $out[] = '- ' . $line;
        }
        foreach ($added as $line) {
            $out[] = '+ ' . $line;
        }

        return implode("\n", $out) . "\n";
    }
}
