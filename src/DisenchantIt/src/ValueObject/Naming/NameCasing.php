<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Naming;

/**
 * Casing strategy applied to a generated or dictionary-supplied constant name.
 * Closed, genuinely-enumerable set, so a backed enum (per AGENTS.md / ADR-0003)
 * rather than a validated string.
 */
enum NameCasing: string
{
    case UpperSnake = 'upper_snake';
    case LowerSnake = 'lower_snake';
    case None = 'none';
}
