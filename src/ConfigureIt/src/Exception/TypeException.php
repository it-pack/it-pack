<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Exception;

/**
 * Thrown when a typed accessor receives a value that cannot be represented as
 * the requested scalar type, or when a required configuration key is missing.
 *
 * This is the consumption-edge counterpart to {@see CastException} (which fires
 * during pipeline casting). Typed accessors surface configuration mistakes at
 * the read site rather than letting a `mixed` value flow into application code.
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot"
 */
class TypeException extends ConfigurationException
{
    public static function missing(string $path): self
    {
        return new self(sprintf('Required configuration key "%s" is missing.', $path));
    }

    public static function typeMismatch(string $path, string $expected, string $actual): self
    {
        return new self(
            sprintf('Configuration key "%s" expected %s, got %s.', $path, $expected, $actual),
        );
    }

    public static function notList(string $path, string $actual): self
    {
        return new self(
            sprintf('Configuration key "%s" expected a list, got %s.', $path, $actual),
        );
    }
}
