<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\ScopeFilterInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;

/**
 * Default candidate filter. Strings keep the original rules (skip enum backing
 * values, exception messages, format strings; keep identifier-shaped values).
 * Numbers are promoted only when they carry a naming hint (unit / comment /
 * assigned name) and are not a trivial value (0, 1, 0.0, 1.0) — that is what
 * keeps magic numbers in conditionals and bare expressions from being promoted.
 */
final readonly class CandidateFilter implements CandidateFilterInterface
{
    /** Values too small/generic to be worth a constant of their own. */
    private const array TRIVIAL_NUMERIC_VALUES = ['0', '1', '0.0', '1.0'];

    public function __construct(
        private RoleClassifierInterface $classifier,
        private ScopeFilterInterface    $scopeFilter,
    ) {
    }

    /**
     * @param list<LiteralInterface> $literals
     * @return list<LiteralInterface>
     */
    public function filterCandidates(array $literals, ?string $relativePath = null): array
    {
        $path = $relativePath ?? '';

        return array_values(array_filter(
            $literals,
            fn (LiteralInterface $lit): bool =>
                $this->passesRoleAndKind($lit) && $this->scopeFilter->isAllowed($lit, $path),
        ));
    }

    private function passesRoleAndKind(LiteralInterface $lit): bool
    {
        return match ($lit->kind) {
            LiteralKind::String->value => $this->keepString($lit),
            LiteralKind::Int->value, LiteralKind::Float->value => $this->keepNumber($lit),
            default => false,
        };
    }

    private function keepString(LiteralInterface $lit): bool
    {
        if ($lit->role !== LiteralRole::EnumBackingValue
            && $lit->role !== LiteralRole::ClassConstValue
            && !in_array($lit->role, [LiteralRole::ExceptionMessage, LiteralRole::FormatString], true)
            && $this->classifier->looksIdentifierLike($lit->value)) {
            return true;
        }

        // A fully-qualified class name (`App\Foo\Bar`) is a reference, not a
        // magic value. Excluded from generic promotion by default; when the
        // opt-in `::class` converter is enabled it becomes a candidate again so
        // that target (registered ahead of the fallback) can rewrite it.
        if ($lit->isClassnameString) {
            return $this->scopeFilter->isConvertFqcnEnabled();
        }

        // Structured string bodies (SQL / JSON) legitimately contain spaces and
        // are recognised by their sub-kind rather than identifier shape — keep
        // them as candidates (e.g. repeated heredoc SQL bodies).
        return $lit->subkind === LiteralSubkind::Sql
            || $lit->subkind === LiteralSubkind::Json;
    }

    private function keepNumber(LiteralInterface $lit): bool
    {
        if (in_array($lit->value, self::TRIVIAL_NUMERIC_VALUES, true)) {
            return false;
        }

        // A numeric constant's own declared value / enum backing value is never
        // a usage site (defensive — the visitor already excludes these).
        if ($lit->role === LiteralRole::EnumBackingValue || $lit->role === LiteralRole::ClassConstValue) {
            return false;
        }

        // Only promote when at least one naming hint is present; a bare literal
        // in a comparison/branch carries none.
        return $lit->namingContext !== null && $lit->namingContext->hasHint();
    }
}
