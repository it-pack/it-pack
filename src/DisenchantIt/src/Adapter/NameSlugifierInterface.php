<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

/**
 * Port for turning raw literal / variable names into valid PHP identifier
 * fragments. Shared by the promotion targets so case naming can never drift
 * between BackedEnumTarget and FlagSetTarget (REFACTORING_PLAN.md M1,
 * REVIEW.md F1). Kept adapter-internal: both consumers live here and no
 * external extension point is warranted yet.
 */
interface NameSlugifierInterface
{
    /**
     * PascalCases a raw value into a valid identifier fragment: keeps only
     * alphanumerics, title-cases each word, guards a leading digit with 'V',
     * and returns $fallback when nothing survives cleaning.
     *
     * Callers pass their domain-specific fallback explicitly ('Value' for
     * enum cases, 'Flag' for flag-set base names).
     */
    public function pascalCase(string $value, string $fallback = 'Value'): string;
}
