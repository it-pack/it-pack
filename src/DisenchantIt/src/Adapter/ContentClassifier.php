<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ContentClassifierInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;

/**
 * Default ContentClassifier: recognises a literal's content sub-kind from its
 * already-parsed value/raw token and its detected role. Pure string inspection
 * plus one role-aware numeric rule — no execution, no (re)parsing of the
 * analysed source, in keeping with the source-text-safety hard rule.
 *
 * The heuristics here are the Phase-0 foundation; later phases refine specific
 * sub-kinds (e.g. Bitmask needs parent bitwise-op context) and may add cases
 * that require more than the literal's own fields.
 */
final readonly class ContentClassifier implements ContentClassifierInterface
{
    public function classify(LiteralInterface $lit, bool $inBitwiseContext = false): LiteralSubkind
    {
        if ($lit->kind === LiteralKind::String->value) {
            return $this->classifyString($lit->value);
        }

        if ($lit->kind === LiteralKind::Int->value) {
            return $this->classifyInt((int) $lit->value, $lit->role, $inBitwiseContext);
        }

        return LiteralSubkind::Unknown;
    }

    private function classifyString(string $value): LiteralSubkind
    {
        if ($value === '') {
            return LiteralSubkind::Unknown;
        }

        // JSON payloads begin with an object or array brace.
        $first = $value[0];
        if ($first === '{' || $first === '[') {
            return LiteralSubkind::Json;
        }

        // SQL statements (used mainly for heredoc bodies in a later phase).
        if (preg_match('#^(select|insert|update|delete|create table)\b#i', $value) === 1) {
            return LiteralSubkind::Sql;
        }

        // Regular expressions: delimited by /…/, ~…~, or #…# with no internal
        // spaces. The opening and closing delimiter must be the same character.
        if ($this->looksRegex($value)) {
            return LiteralSubkind::Regex;
        }

        // Semantic versions: 1.2, 1.2.3, ...
        if (preg_match('#^\d+\.\d+(\.\d+)*$#', $value) === 1) {
            return LiteralSubkind::Version;
        }

        // URLs: scheme://, protocol-relative //, or a /path-like root.
        if (preg_match('#^(https?://|//|/[a-z0-9_.\-]+)#i', $value) === 1) {
            return LiteralSubkind::Url;
        }

        return LiteralSubkind::Unknown;
    }

    private function looksRegex(string $value): bool
    {
        // Opening delimiter must be one of / ~ #, and the closing character must
        // match it exactly, with no internal whitespace.
        $delimiters = ['/', '~', '#'];
        $first = $value[0];

        if (!in_array($first, $delimiters, true)) {
            return false;
        }

        if (str_contains($value, ' ')) {
            return false;
        }

        return $value[strlen($value) - 1] === $first;
    }

    private function classifyInt(int $value, LiteralRole $role, bool $inBitwiseContext): LiteralSubkind
    {
        // Status / HTTP-status codes: a three-digit number in a comparison or
        // branch context. Refined further in later phases.
        if ($value >= 100
            && $value <= 599
            && in_array($role, [LiteralRole::Comparison, LiteralRole::BranchValue], true)
        ) {
            return LiteralSubkind::StatusCode;
        }

        // Bitmask: a power-of-two integer that participates in a bitwise
        // operation. Powers of two are the atoms of a flag set; a lone or
        // non-power-of-two value (e.g. 3, 100) is not.
        if ($inBitwiseContext && $value > 0 && ($value & ($value - 1)) === 0) {
            return LiteralSubkind::Bitmask;
        }

        return LiteralSubkind::Unknown;
    }
}
