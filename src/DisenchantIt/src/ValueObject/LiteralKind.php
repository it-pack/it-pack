<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Closed set of literal kinds DisenchantIt can detect and promote. Genuinely
 * closed internal state, so a backed enum (per AGENTS.md) rather than a
 * validated-string name. The string cases are the canonical `kind` values
 * carried on each literal value object.
 */
enum LiteralKind: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
}
