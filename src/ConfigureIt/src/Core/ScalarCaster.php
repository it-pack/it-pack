<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Exception\TypeException;

/**
 * Coercion rules shared by typed snapshot accessors and freeze-time validation.
 *
 * Mirrors the leniency of {@see CastingProcessor} so that environment-sourced
 * values (numeric strings, truthy strings) read cleanly through typed accessors,
 * while still failing fast on genuinely incompatible values. Every failure
 * raises a domain {@see TypeException} rather than leaking vendor types.
 *
 * @internal consumed by {@see ConfigurationSnapshot} and {@see TypedSnapshot}
 */
final class ScalarCaster
{
    public static function toInt(mixed $value, string $path): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value)) {
            return (int) $value;
        }

        if (\is_string($value) && \is_numeric($value) && !\str_contains($value, '.')) {
            return (int) $value;
        }

        throw TypeException::typeMismatch($path, 'int', self::describe($value));
    }

    public static function toFloat(mixed $value, string $path): float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }

        if (\is_string($value) && \is_numeric($value)) {
            return (float) $value;
        }

        throw TypeException::typeMismatch($path, 'float', self::describe($value));
    }

    public static function toBool(mixed $value, string $path): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return $value === 1;
        }

        if (\is_string($value)) {
            return match (\strtolower(\trim($value))) {
                'true', '1', 'on', 'yes', 'y' => true,
                'false', '0', 'off', 'no', 'n' => false,
                default => throw TypeException::typeMismatch($path, 'bool', self::describe($value)),
            };
        }

        throw TypeException::typeMismatch($path, 'bool', self::describe($value));
    }

    public static function toString(mixed $value, string $path): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value)) {
            return (string) $value;
        }

        throw TypeException::typeMismatch($path, 'string', self::describe($value));
    }

    /**
     * @return array<int, mixed>
     */
    public static function toList(mixed $value, string $path): array
    {
        if (\is_array($value) && \array_is_list($value)) {
            return $value;
        }

        throw TypeException::notList($path, self::describe($value));
    }

    /**
     * @return array<string, mixed>
     */
    public static function toMap(mixed $value, string $path): array
    {
        if (\is_array($value) && !\array_is_list($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        throw TypeException::typeMismatch($path, 'map', self::describe($value));
    }

    private static function describe(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        return \get_debug_type($value);
    }
}
