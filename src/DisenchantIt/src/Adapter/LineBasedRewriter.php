<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\RewriterInterface;
use ItPack\DisenchantIt\ValueObject\InsertionInterface;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;

/**
 * Line-based implementation of RewriterInterface: no AST reprinting, no
 * nette/php-generator Printer, no full-file regeneration. Every line NOT
 * touched by an artifact is left byte-for-byte identical.
 *
 * Consumes a list of PromotionArtifacts — the single shape every promotion
 * target emits.
 *
 * Two operations, always in this order:
 *
 *   1. Literal-to-reference replacement, one exact line at a time, using each
 *      occurrence's recorded line number from the detector.
 *   2. Declaration insertion — consts inside the class body (after the opening
 *      brace), enums at file top level (after the class's closing brace) — from
 *      the BOTTOM of the file upward so earlier insertions don't shift the line
 *      numbers of insertions still pending.
 */
final class LineBasedRewriter implements RewriterInterface
{
    /**
     * @param list<PromotionArtifactInterface> $items
     */
    public function rewrite(string $code, array $items, bool $dryRun = false): string
    {
        if ($items === []) {
            return $code;
        }

        // Dry-run: never apply an edit. The caller (Disenchanter) records the
        // planned edits through the recorder port so a diff/report can be built
        // without mutating the source.
        if ($dryRun) {
            return $code;
        }

        $lines = explode("\n", $code);

        // --- Step 1: literal -> reference ---

        // Single-line edits are in-place (no line shift); multi-line edits (e.g.
        // heredoc bodies) replace a span of lines and therefore change the line
        // count. Process every replacement ordered by start line, tracking a
        // running delta so later edits still land on the correct line.
        $replacements = [];
        foreach ($items as $artifact) {
            foreach ($artifact->replacements as $replacement) {
                $replacements[] = $replacement;
            }
        }

        usort($replacements, static fn ($a, $b): int => $a->line <=> $b->line);

        $delta = 0;
        foreach ($replacements as $replacement) {
            $index = $replacement->line - 1 - $delta; // token lines are 1-based

            $isSpan = $replacement->endLine !== null && $replacement->endLine > $replacement->line;

            if ($isSpan) {
                $endIndex = $replacement->endLine - 1 - $delta;

                if (!isset($lines[$index]) || !isset($lines[$endIndex])) {
                    continue; // defensive: stale line number, skip rather than corrupt
                }

                $span = implode("\n", array_slice($lines, $index, $endIndex - $index + 1));
                $pos = strpos($span, $replacement->needle);
                $newSpan = $pos !== false
                    ? substr_replace($span, $replacement->replacement, $pos, strlen($replacement->needle))
                    : $replacement->replacement;

                $newLines = explode("\n", $newSpan);
                $removed = $endIndex - $index + 1;
                array_splice($lines, $index, $removed, $newLines);
                $delta += $removed - count($newLines);

                continue;
            }

            if (!isset($lines[$index])) {
                continue; // defensive: stale line number, skip rather than corrupt an unrelated line
            }

            $pos = strpos($lines[$index], $replacement->needle);
            if ($pos !== false) {
                $lines[$index] = substr_replace(
                    $lines[$index],
                    $replacement->replacement,
                    $pos,
                    strlen($replacement->needle),
                );
            }
        }

        // --- Step 2: insert declarations, bottom-of-file first ---

        $insertionPoints = $this->planInsertions($lines, $items);

        krsort($insertionPoints);

        foreach ($insertionPoints as $afterLineIndex => $declarationLines) {
            array_splice($lines, $afterLineIndex + 1, 0, $declarationLines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param list<PromotionArtifactInterface> $artifacts
     * @return array<int, list<string>> line-index => lines to insert after it
     */
    private function planInsertions(array $lines, array $artifacts): array
    {
        /** @var array<string, list<InsertionInterface>> $insertionsByClass */
        $insertionsByClass = [];
        foreach ($artifacts as $artifact) {
            foreach ($artifact->insertions as $insertion) {
                $insertionsByClass[$insertion->className][] = $insertion;
            }
        }

        $insertionPoints = [];

        foreach ($insertionsByClass as $className => $classInsertions) {
            if ($classInsertions[0]->topLevel) {
                $anchorIndex = $this->findClassClosingBraceLine($lines, $className);
                if ($anchorIndex === null) {
                    continue; // class not found in this file — not this file's insertion to apply
                }

                // Idempotency: a previously-generated enum of the same name is
                // left untouched, so a second pass adds nothing.
                if ($this->enumAlreadyExists($lines, $classInsertions)) {
                    continue;
                }

                $declarationLines = $this->flattenLines($classInsertions);
                if ($declarationLines === []) {
                    continue;
                }

                $insertionPoints[$anchorIndex] = array_merge($insertionPoints[$anchorIndex] ?? [], $declarationLines);

                continue;
            }

            // Class-body insertion: consts go right after the opening brace.
            $braceLineIndex = $this->findClassOpeningBraceLine($lines, $className);
            if ($braceLineIndex === null) {
                continue;
            }

            $existingConstantNames = $this->findExistingConstantNames($lines, $braceLineIndex);

            $declarationLines = [];
            foreach ($classInsertions as $insertion) {
                foreach ($insertion->lines as $lineText) {
                    $constName = $this->constantNameFromLine($lineText);
                    if ($constName !== null && in_array($constName, $existingConstantNames, true)) {
                        // Already declared — never re-declare.
                        continue;
                    }
                    $declarationLines[] = $lineText;
                }
            }

            if ($declarationLines === []) {
                continue;
            }

            $insertionPoints[$braceLineIndex] = array_merge($insertionPoints[$braceLineIndex] ?? [], $declarationLines);
        }

        return $insertionPoints;
    }

    /**
     * @param list<InsertionInterface> $insertions
     * @return list<string>
     */
    private function flattenLines(array $insertions): array
    {
        $lines = [];
        foreach ($insertions as $insertion) {
            foreach ($insertion->lines as $lineText) {
                $lines[] = $lineText;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param list<InsertionInterface> $insertions
     */
    private function enumAlreadyExists(array $lines, array $insertions): bool
    {
        $name = null;
        foreach ($insertions as $insertion) {
            foreach ($insertion->lines as $lineText) {
                if (preg_match('/\benum\s+([A-Za-z_][A-Za-z0-9_]*)/', $lineText, $m) === 1) {
                    $name = $m[1];
                    break 2;
                }
            }
        }

        if ($name === null) {
            return false;
        }

        foreach ($lines as $line) {
            if (preg_match('/\benum\s+' . preg_quote($name, '/') . '\b/', $line) === 1) {
                return true;
            }
        }

        return false;
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

        for ($i = $braceLineIndex, $iMax = count($lines); $i < $iMax; $i++) {
            $depth += substr_count($lines[$i], '{');
            $depth -= substr_count($lines[$i], '}');

            $constName = $this->constantNameFromLine($lines[$i]);
            if ($constName !== null) {
                $names[] = $constName;
            }

            if ($depth <= 0 && $i > $braceLineIndex) {
                break; // reached the class's own closing brace
            }
        }

        return $names;
    }

    /**
     * Extracts the constant name from a `const [type] NAME =` declaration
     * line, or null when the line is not a const declaration. Single source
     * of truth for both the duplicate guard in planInsertions() and the
     * existing-name collection in findExistingConstantNames().
     */
    private function constantNameFromLine(string $line): ?string
    {
        if (preg_match('/\bconst\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?([A-Z_][A-Z0-9_]*)\s*=/', $line, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Builds the classlike-declaration match pattern for a given name. Single
     * source of truth so the class|enum|interface|trait alternation cannot
     * drift between call sites.
     */
    private function classlikeDeclarationPattern(string $className): string
    {
        return sprintf(
            '/\b(?:class|enum|interface|trait)\s+%s\b/',
            preg_quote($className, '/'),
        );
    }

    /**
     * Locates a classlike body's brace lines in one walk: scans for the first
     * declaration matching the name, then tracks depth forward, recording the
     * opening `{` line and the depth-balanced closing `}` line independently.
     * Either member may be null when the source shape doesn't produce it
     * (e.g. a single-line `class C {}` has an opening but no later closing
     * line), and callers keep their own tolerance via the two wrappers below.
     *
     * @param list<string> $lines
     * @return array{opening: int|null, closing: int|null}|null null when no
     *         declaration with that name exists in this file
     */
    private function findClassBodyBraces(array $lines, string $className): ?array
    {
        $declarationPattern = $this->classlikeDeclarationPattern($className);

        foreach ($lines as $index => $line) {
            if (preg_match($declarationPattern, $line) !== 1) {
                continue;
            }

            $opening = null;
            $closing = null;
            $depth = 0;
            for ($i = $index, $iMax = count($lines); $i < $iMax; $i++) {
                if ($opening === null && str_contains($lines[$i], '{')) {
                    $opening = $i;
                }
                $depth += substr_count($lines[$i], '{');
                $depth -= substr_count($lines[$i], '}');

                if ($depth <= 0 && $i > $index) {
                    $closing = $i;
                    break;
                }
            }

            return ['opening' => $opening, 'closing' => $closing];
        }

        return null;
    }

    /**
     * Finds the line index of the `{` that opens the given class/enum body.
     *
     * @param list<string> $lines
     */
    private function findClassOpeningBraceLine(array $lines, string $className): ?int
    {
        $braces = $this->findClassBodyBraces($lines, $className);

        return $braces === null ? null : $braces['opening'];
    }

    /**
     * Finds the line index of the `}` that closes the given class/enum body,
     * tracking brace depth from the declaration. Used to insert a top-level enum
     * after the class (enums cannot be nested inside a class).
     *
     * @param list<string> $lines
     */
    private function findClassClosingBraceLine(array $lines, string $className): ?int
    {
        $braces = $this->findClassBodyBraces($lines, $className);

        return $braces === null ? null : $braces['closing'];
    }
}
