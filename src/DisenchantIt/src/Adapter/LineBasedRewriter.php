<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\RewriterInterface;
use ItPack\DisenchantIt\ValueObject\ConstantDeclaration;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\Insertion;
use ItPack\DisenchantIt\ValueObject\InsertionInterface;
use ItPack\DisenchantIt\ValueObject\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Replacement;
use ItPack\DisenchantIt\ValueObject\TargetKind;

/**
 * Line-based implementation of RewriterInterface: no AST reprinting, no
 * nette/php-generator Printer, no full-file regeneration. Every line NOT
 * touched by an artifact is left byte-for-byte identical.
 *
 * Consumes a list of PromotionArtifacts (the single shape every promotion target
 * emits). A thin shim still accepts the legacy ConstantPlacementInterface so any
 * not-yet-migrated caller keeps working for one transition step (see
 * docs/ADDITIONAL_FEATURES_ROADMAP.md, Phase 2).
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
     * @param list<PromotionArtifactInterface|ConstantPlacementInterface> $items
     */
    public function rewrite(string $code, array $items): string
    {
        $artifacts = [];
        foreach ($items as $item) {
            if ($item instanceof PromotionArtifactInterface) {
                $artifacts[] = $item;
            } elseif ($item instanceof ConstantPlacementInterface) {
                $artifacts[] = $this->artifactFromPlacement($item);
            }
        }

        if ($artifacts === []) {
            return $code;
        }

        $lines = explode("\n", $code);

        // --- Step 1: literal -> reference, one recorded line at a time ---

        foreach ($artifacts as $artifact) {
            foreach ($artifact->replacements as $replacement) {
                $index = $replacement->line - 1; // token lines are 1-based

                if (!isset($lines[$index])) {
                    continue; // defensive: stale line number, skip rather than corrupt an unrelated line
                }

                $pos = strpos($lines[$index], $replacement->needle);
                if ($pos !== false) {
                    $lines[$index] = substr_replace($lines[$index], $replacement->replacement, $pos, strlen($replacement->needle));
                }
            }
        }

        // --- Step 2: insert declarations, bottom-of-file first ---

        $insertionPoints = $this->planInsertions($lines, $artifacts);

        krsort($insertionPoints);

        foreach ($insertionPoints as $afterLineIndex => $declarationLines) {
            array_splice($lines, $afterLineIndex + 1, 0, $declarationLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Temporary compatibility shim: rebuilds the artifact a ConstantPlacement
     * would have produced under the old pipeline. REMOVE once all callers emit
     * PromotionArtifacts directly.
     */
    private function artifactFromPlacement(ConstantPlacementInterface $placement): PromotionArtifactInterface
    {
        $insertions = [];
        $replacements = [];

        if ($placement->occurrences !== []) {
            $declarationLine = new ConstantDeclaration(
                name: $placement->constantName,
                valueExpression: $placement->occurrences[0]->raw,
                typeKeyword: $placement->typeKeyword(),
                visibility: 'private',
            )->toCodeLine();

            $insertions[] = new Insertion($placement->className, [$declarationLine], false);

            foreach ($placement->occurrences as $lit) {
                $replacements[] = new Replacement($lit->line, $lit->needle(), 'self::' . $placement->constantName);
            }
        }

        return new PromotionArtifact($insertions, $replacements, TargetKind::Const, [$placement]);
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
            } else {
                $braceLineIndex = $this->findClassOpeningBraceLine($lines, $className);
                if ($braceLineIndex === null) {
                    continue;
                }

                $existingConstantNames = $this->findExistingConstantNames($lines, $braceLineIndex);

                $declarationLines = [];
                foreach ($classInsertions as $insertion) {
                    foreach ($insertion->lines as $lineText) {
                        if (preg_match('/\bconst\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?([A-Z_][A-Z0-9_]*)\s*=/', $lineText, $m) === 1
                            && in_array($m[1], $existingConstantNames, true)) {
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
     * Finds the line index of the `}` that closes the given class/enum body,
     * tracking brace depth from the declaration. Used to insert a top-level enum
     * after the class (enums cannot be nested inside a class).
     *
     * @param list<string> $lines
     */
    private function findClassClosingBraceLine(array $lines, string $className): ?int
    {
        $declarationPattern = sprintf(
            '/\b(?:class|enum|interface|trait)\s+%s\b/',
            preg_quote($className, '/'),
        );

        foreach ($lines as $index => $line) {
            if (preg_match($declarationPattern, $line) !== 1) {
                continue;
            }

            $depth = 0;
            for ($i = $index, $iMax = count($lines); $i < $iMax; $i++) {
                $depth += substr_count($lines[$i], '{');
                $depth -= substr_count($lines[$i], '}');

                if ($depth <= 0 && $i > $index) {
                    return $i;
                }
            }
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
        $declarationPattern = sprintf(
            '/\b(?:class|enum|interface|trait)\s+%s\b/',
            preg_quote($className, '/'),
        );

        foreach ($lines as $index => $line) {
            if (preg_match($declarationPattern, $line) !== 1) {
                continue;
            }

            for ($i = $index, $iMax = count($lines); $i < $iMax; $i++) {
                if (str_contains($lines[$i], '{')) {
                    return $i;
                }
            }
        }

        return null;
    }
}
