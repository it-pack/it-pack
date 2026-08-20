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

    /**
     * The PHP type keyword used in a `private const <kind> NAME = ...;`
     * declaration. Today the keyword equals the backed enum value, but this
     * helper is the single place that maps a detected kind to its emitted
     * keyword, so the two can never drift apart.
     */
    public function toPhpKeyword(): string
    {
        return match ($this) {
            self::String => 'string',
            self::Int => 'int',
            self::Float => 'float',
        };
    }

    /**
     * Parse a stored kind string (e.g. a ConstantPlacement::type value) back
     * into the enum. Thin alias over `from()` for call sites that mean "a kind"
     * rather than an arbitrary backed value.
     */
    public static function fromKind(string $kind): self
    {
        return self::from($kind);
    }
}
