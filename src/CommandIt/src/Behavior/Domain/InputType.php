<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

/**
 * The closed set of input coercion type names, with a legacy-alias normalizer.
 *
 * Centralizing the literals keeps the {@see InputCoercionBehavior} match arms
 * free of stringly-typed magic values and documents the `double` → `float`
 * legacy alias in one place.
 *
 * @internal
 */
final class InputType
{
    public const STRING = 'string';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';

    /**
     * Legacy alias emitted by some type systems and CLIs; normalized to {@see self::FLOAT}.
     */
    public const DOUBLE = 'double';

    public static function normalize(string $type): string
    {
        return match ($type) {
            self::DOUBLE => self::FLOAT,
            default => $type,
        };
    }
}
