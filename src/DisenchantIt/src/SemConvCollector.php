<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\SemConvConfigInterface;
use RuntimeException;

/**
 * Builds the shared interface file's source text from a set of
 * ConstantPlacements. Read-only with respect to the original source files —
 * this only ever produces ONE new file's content; SemConvAliasRewriter
 * (below) is what touches the original classes.
 */
final readonly class SemConvCollector implements SemConvCollectorInterface
{
    public function __construct(
        private SemConvConfigInterface $config,
    ) {
    }

    /**
     * @param list<ConstantPlacementInterface> $placements
     * @param string|null $existingSource the CURRENT content of the SemConv
     *        file, if one already exists from a prior run — passed in
     *        explicitly (rather than this class reading the filesystem
     *        itself) to keep it a pure string-in/string-out transform, the same
     *        discipline as the rest of DisenchantIt. Without this, a second
     *        run whose Step 1 finds fewer NEW candidates than a first run
     *        found (expected, since idempotent Step 1 finds nothing left to
     *        promote) would regenerate an interface containing only this
     *        run's placements — silently discarding every constant a prior
     *        run had already collected. Found and fixed via an actual
     *        two-runs-in-a-row test, not by inspection.
     * @return string
     */
    public function collect(array $placements, ?string $existingSource = null): string
    {
        /** @var array<string, array{type: string, value: string}> $entries */
        $entries = $this->extractExistingEntries($existingSource);
        $collisions = [];

        foreach ($placements as $placement) {
            $name = $placement->constantName;
            $canonical = $this->canonicalValue($placement);

            if (isset($entries[$name]) && $entries[$name]['value'] !== $canonical) {
                $collisions[$name][] = $placement;
                continue;
            }

            $entries[$name] = [
                'type' => $placement->type,
                'value' => $canonical,
            ];
        }

        if ($collisions !== []) {
            throw new RuntimeException(
                sprintf(
                    'SemConv collection found name collisions with differing values for: %s. ' .
                    'These constant names are used for different values in different classes — ' .
                    'resolve the naming conflict manually before generating a shared SemConv file ' .
                    '(rename one of them, or accept them as genuinely separate concepts and exclude ' .
                    'one from the collection).',
                    implode(', ', array_keys($collisions)),
                ),
            );
        }

        ksort($entries);

        return $this->render($entries);
    }

    /**
     * Canonicalizes a placement's value to a var_export-style string keyed by
     * its declared type, so `0x1F` and `31` both become `'31'` and share one
     * SemConv entry (numeric) while strings stay quoted.
     */
    private function canonicalValue(ConstantPlacementInterface $placement): string
    {
        return match (LiteralKind::fromKind($placement->type)) {
            LiteralKind::Int => var_export((int) $placement->value, true),
            LiteralKind::Float => var_export((float) $placement->value, true),
            LiteralKind::String => var_export($placement->value, true),
        };
    }

    /**
     * Parses `public const [type] NAME = <value>;` lines out of a previously
     * generated SemConv file so collect() can merge into them rather than
     * starting from nothing. The deliberately simple regex rather than a full
     * parse — render() below fully controls this file's shape, so it's always
     * in the exact form this regex expects, as long as nobody hand-edits it
     * into a different shape.
     *
     * @return array<string, array{type: string, value: string}>
     */
    private function extractExistingEntries(?string $existingSource): array
    {
        if ($existingSource === null) {
            return [];
        }

        $entries = [];
        if (preg_match_all(
            '/public const (?:(int|float|string)\s+)?([A-Z_][A-Z0-9_]*) = (.+?);/',
            $existingSource,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $type = $match[1] !== '' ? $match[1] : 'string';
                $name = $match[2];
                $entries[$name] = [
                    'type' => $type,
                    'value' => $this->canonicalizeExpr($match[3], $type),
                ];
            }
        }

        return $entries;
    }

    /**
     * Reduces a previously-rendered value expression to the same canonical
     * form canonicalValue() produces, so a prior run's `public const int
     * MASK = 31;` equals a fresh `0x1F` placement (both `'31'`).
     */
    private function canonicalizeExpr(string $expr, string $type): string
    {
        $expr = trim($expr);

        return match ($type) {
            'int' => var_export((int) $expr, true),
            'float' => var_export((float) $expr, true),
            default => var_export(stripcslashes(trim($expr, "'")), true),
        };
    }

    /**
     * @param array<string, array{type: string, value: string}> $entries
     */
    private function render(array $entries): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            "namespace {$this->config->namespace};",
            '',
            '/**',
            ' * Generated by DisenchantIt (SemConvCollector) — a shared, dependency-free',
            ' * registry of constants collected from across the codebase, modeled after',
            ' * OpenTelemetry SemConv: one canonical name+value pair per concept, referenced',
            ' * by classes rather than duplicated across them.',
            ' *',
            ' * This interface intentionally holds ONLY constants, no methods — there is no',
            ' * business logic here, only a shared vocabulary. Classes typically reference',
            ' * these indirectly, through their OWN local constant aliasing one of these',
            ' * (see SemConvAliasRewriter) — existing `self::FOO` call sites in those',
            ' * classes are unaffected by this file existing.',
            ' *',
            ' * Regenerating this file MERGES with its existing content — re-running the',
            ' * scan adds newly-found constants without discarding ones already collected',
            ' * by a previous run.',
            ' */',
            "interface {$this->config->interfaceName}",
            '{',
        ];

        foreach ($entries as $name => $entry) {
            $lines[] = sprintf('    public const %s %s = %s;', $entry['type'], $name, $entry['value']);
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
