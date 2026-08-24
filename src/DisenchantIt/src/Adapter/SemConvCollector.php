<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\SemConvCollectorInterface;
use ItPack\DisenchantIt\Contract\SemConvStoreInterface;
use ItPack\DisenchantIt\Exception\SemConvCollisionException;
use ItPack\DisenchantIt\ValueObject\ConstantDeclaration;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\SemConvConfigInterface;
use ItPack\DisenchantIt\ValueObject\Visibility;

/**
 * Builds the shared interface file's source text from a set of
 * ConstantPlacements. Read-only with respect to the original source files —
 * this only ever produces ONE new file's content; SemConvAliasRewriter
 * (below) is what touches the original classes.
 *
 * When an optional {@see SemConvStoreInterface} is supplied, collect() loads
 * the prior registry from it when no explicit $existingSource is given, and
 * persists the merged result back to it — giving stable, accumulating SemConv
 * names across runs without Core touching the filesystem.
 */
final readonly class SemConvCollector implements SemConvCollectorInterface
{
    public function __construct(
        private SemConvConfigInterface $config,
        private ?SemConvStoreInterface $store = null,
    ) {
    }

    /**
     * @param list<ConstantPlacementInterface> $placements
     * @param string|null $existingSource the CURRENT content of the SemConv
     *        file, if one already exists from a prior run — passed in
     *        explicitly (rather than this class reading the filesystem
     *        itself) to keep it a pure string-in/string-out transform, the same
     *        discipline as the rest of DisenchantIt. When null and a store is
     *        wired, the existing source is loaded from the store instead.
     *        Without this, a second run whose Step 1 finds fewer NEW candidates
     *        than a first run found (expected, since idempotent Step 1 finds
     *        nothing left to promote) would regenerate an interface containing
     *        only this run's placements — silently discarding every constant a
     *        prior run had already collected. Found and fixed via an actual
     *        two-runs-in-a-row test, not by inspection.
     * @return string
     */
    public function collect(array $placements, ?string $existingSource = null): string
    {
        if ($existingSource === null && $this->store !== null) {
            $existingSource = $this->store->load();
        }

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
            throw new SemConvCollisionException(array_keys($collisions));
        }

        ksort($entries);

        $rendered = $this->render($entries);

        // Persist the merged registry back to the store (when wired) so the
        // next run loads these entries and keeps names stable. No-op without a
        // store — the pure string transform is unchanged.
        if ($this->store !== null) {
            $this->store->save($rendered);
        }

        return $rendered;
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
            $lines[] = new ConstantDeclaration(
                name: $name,
                valueExpression: $entry['value'],
                typeKeyword: $entry['type'],
                visibility: Visibility::Public,
            )->toCodeLine();
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
