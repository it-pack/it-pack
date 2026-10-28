<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Closed set of promotion targets DisenchantIt can emit for a detected group of
 * literals. Genuinely closed internal state, so a backed enum (per AGENTS.md /
 * ADR-0003) rather than a validated-string name.
 *
 *  - Const       a `private const` declaration (today's behavior).
 *  - Enum        a backed enum with a case per distinct literal value.
 *  - FlagSet     a set of bit-flag constants (Phase 3).
 *  - Fqcn        a fully-qualified class name rewritten to `<Short>::class`
 *                (Phase 4, opt-in `::class` converter; emits no declaration).
 *  - SharedConst a constant collected into the shared SemConv registry (future).
 */
enum TargetKind: string
{
    case Const = 'const';
    case Enum = 'enum';
    case FlagSet = 'flag_set';
    case Fqcn = 'fqcn';
    // @phpstan-ignore shipmonk.deadEnumCase
    case SharedConst = 'shared_const';
}
