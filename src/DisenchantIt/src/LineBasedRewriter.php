<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

/**
 * Line-based implementation of RewriterInterface: no AST reprinting, no
 * nette/php-generator Printer, no full-file regeneration. Every line NOT
 * touched by a placement is left byte-for-byte identical.
 *
 * Two operations, always in this order:
 *
 *   1. Literal-to-constant-reference replacement, one exact line at a time,
 *      using each occurrence's recorded line number from the detector.
 *      Done FIRST, before the constant declarations are inserted — doing it
 *      in the other order would let a blanket-style replacement clobber the
 *      constant's own declaration line (the bug found against the
 *      nette/php-generator-based approach). Operating strictly per-recorded-
 *      line rather than file-wide also means this is naturally immune to
 *      that failure mode even without the ordering rule — but the ordering
 *      is kept anyway as defense in depth.
 *
 *   2. Constant declaration insertion, one class at a time, positioned right
 *      after that class's opening brace. Insertions are applied in a single
 *      pass from the BOTTOM of the file upward (highest line number first),
 *      so inserting a line never shifts the line numbers of insertion
 *      points still queued above it.
 */
final class LineBasedRewriter implements RewriterInterface
{
    /**
     * @param list<ConstantPlacement> $placements
     */
    public function rewrite(string $code, array $placements): string
    {
        if ($placements === []) {
            return $code;
        }

        $lines = explode("\n", $code);

        // --- Step 1: literal -> self::CONST, one recorded line at a time ---

        foreach ($placements as $placement) {
            foreach ($placement->occurrences as $occurrence) {
                $index = $occurrence->line - 1; // token lines are 1-based

                if (!isset($lines[$index])) {
                    continue; // defensive: stale line number, skip rather than corrupt an unrelated line
                }

                $needle = var_export($occurrence->value, true);
                $replacement = 'self::' . $placement->constantName;

                $pos = strpos($lines[$index], $needle);
                if ($pos !== false) {
                    $lines[$index] = substr_replace($lines[$index], $replacement, $pos, strlen($needle));
                }
            }
        }

        // --- Step 2: insert constant declarations, bottom-of-file first ---

        $insertionsByLine = $this->planInsertions($lines, $placements);

        // Sort insertion points descending so earlier insertions don't shift
        // the line numbers of insertions still pending.
        krsort($insertionsByLine);

        foreach ($insertionsByLine as $afterLineIndex => $declarationLines) {
            array_splice($lines, $afterLineIndex + 1, 0, $declarationLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Finds, for each class that needs a new constant, the line index of
     * that class's opening brace, and builds the declaration text to insert
     * right after it. One class may need several constants (several
     * placements) — all are batched into one insertion at that class's
     * brace so we don't compute stale offsets across multiple passes.
     *
     * @param list<string> $lines
     * @param list<ConstantPlacement> $placements
     * @return array<int, list<string>> line-index => lines to insert after it
     */
    private function planInsertions(array $lines, array $placements): array
    {
        $placementsByClass = [];
        foreach ($placements as $placement) {
            $placementsByClass[$placement->className][] = $placement;
        }

        $insertions = [];

        foreach ($placementsByClass as $className => $classPlacements) {
            $braceLineIndex = $this->findClassOpeningBraceLine($lines, $className);
            if ($braceLineIndex === null) {
                continue; // class not found in this file — not this file's placement to apply
            }

            $existingConstantNames = $this->findExistingConstantNames($lines, $braceLineIndex);

            $declarationLines = [];
            foreach ($classPlacements as $placement) {
                if (in_array($placement->constantName, $existingConstantNames, true)) {
                    // Already declared — either a prior disenchant.php run
                    // already added it, or two placements in this run
                    // resolved to the same name. Never re-declare: PHP
                    // rejects duplicate const declarations outright, and
                    // silently overwriting could corrupt an existing
                    // constant's value.
                    continue;
                }

                $declarationLines[] = sprintf(
                    '    private const string %s = %s;',
                    $placement->constantName,
                    var_export($placement->value, true),
                );
            }

            if ($declarationLines === []) {
                continue;
            }

            $insertions[$braceLineIndex] = array_merge(
                $insertions[$braceLineIndex] ?? [],
                $declarationLines,
            );
        }

        return $insertions;
    }

    /**
     * Scans forward from a class's opening-brace line for `const NAME =`
     * declarations already present, so planInsertions() never emits a
     * duplicate. Stops at the matching closing brace so it doesn't wander
     * into a following class in the same file.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function findExistingConstantNames(array $lines, int $braceLineIndex): array
    {
        $names = [];
        $depth = 0;

        for ($i = $braceLineIndex; $i < count($lines); $i++) {
            $depth += substr_count($lines[$i], '{');
            $depth -= substr_count($lines[$i], '}');

            if (preg_match('/\bconst\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?([A-Z_][A-Z0-9_]*)\s*=/', $lines[$i], $m) === 1) {
                $names[] = $m[1];
            }

            if ($depth <= 0 && $i > $braceLineIndex) {
                break; // reached the class's own closing brace
            }
        }

        return $names;
    }

    /**
     * Finds the line index of the `{` that opens the given class/enum body.
     * Deliberately simple (regex over the class-declaration line, then the
     * next line containing a bare `{`) rather than re-parsing with an AST —
     * this only needs to find ONE brace per class, and the detector has
     * already told us the class exists in this file.
     */
    private function findClassOpeningBraceLine(array $lines, string $className): ?int
    {
        $declarationPattern = sprintf(
            '/\b(?:class|enum|interface|trait)\s+%s\b/',
            preg_quote($className, '/'),
        );

        foreach ($lines as $index => $line) {
            if (preg_match($declarationPattern, $line) !== 1) {
                continue;
            }

            // Brace may be on this same line ("class Foo {") or one of the
            // next few lines ("class Foo\n{" or after an implements/extends
            // clause spanning multiple lines).
            for ($i = $index; $i < count($lines); $i++) {
                if (str_contains($lines[$i], '{')) {
                    return $i;
                }
            }
        }

        return null;
    }
}
