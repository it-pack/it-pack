<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Closed set of promotion targets DisenchantIt can emit for a detected group of
 * literals. Genuinely closed internal state, so a backed enum (per AGENTS.md /
 * ADR-0003) rather than a validated-string name.
 *
 *  - Const       a `private const` declaration (today's behavior).
 *  - Enum        a backed enum with a case per distinct literal value.
 *  - FlagSet     a set of bit-flag constants (Phase 3).
 *  - SharedConst a constant collected into the shared SemConv registry (future).
 */
enum TargetKind: string
{
    case Const = 'const';
    case Enum = 'enum';
    case FlagSet = 'flag_set';
    case SharedConst = 'shared_const';
}
