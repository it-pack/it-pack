<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

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
        private readonly RoleClassifierInterface $classifier,
    ) {
    }

    /**
     * @param list<LiteralInterface> $literals
     * @return list<LiteralInterface>
     */
    public function filterCandidates(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            fn (LiteralInterface $lit): bool =>
                match ($lit->kind) {
                    LiteralKind::String->value => $this->keepString($lit),
                    LiteralKind::Int->value, LiteralKind::Float->value => $this->keepNumber($lit),
                    default => false,
                },
        ));
    }

    private function keepString(LiteralInterface $lit): bool
    {
        return $lit->role !== 'enum_backing_value'
            && $lit->role !== 'class_const_value'
            && !in_array($lit->role, ['exception_message', 'format_string'], true)
            && $this->classifier->looksIdentifierLike($lit->value);
    }

    private function keepNumber(LiteralInterface $lit): bool
    {
        if (in_array($lit->value, self::TRIVIAL_NUMERIC_VALUES, true)) {
            return false;
        }

        // A numeric constant's own declared value / enum backing value is never
        // a usage site (defensive — the visitor already excludes these).
        if ($lit->role === 'enum_backing_value' || $lit->role === 'class_const_value') {
            return false;
        }

        // Only promote when at least one naming hint is present; a bare literal
        // in a comparison/branch carries none.
        return $lit->namingContext !== null && $lit->namingContext->hasHint();
    }
}
