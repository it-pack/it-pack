<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common\Behavior;

/**
 * Shared redacted-field accessors for normalized artifact projections.
 *
 * Centralizes the type-checked extraction of string and int fields from
 * normalized artifact arrays (`ArtifactField::*` keys) with safe defaults.
 * Both the CloudEvents and OpenTelemetry adapters previously inlined the
 * same `is_string` / `is_int` null-coalescing pattern with different method
 * names; this trait removes that duplication without changing behavior.
 *
 * All accessors are strict: a non-string value returns the default (or null)
 * rather than being cast, so redacted projections never silently coerce
 * unexpected types into exported attributes.
 */
trait RedactedFieldAccess
{
    /**
     * Returns the string value at $key, or $default when absent or not a string.
     *
     * @param array<string, mixed> $normalized
     */
    private function redactedString(array $normalized, string $key, string $default): string
    {
        $value = $normalized[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Returns the string value at $key, or null when absent or not a string.
     *
     * @param array<string, mixed> $normalized
     */
    private function redactedStringOrNull(array $normalized, string $key): ?string
    {
        $value = $normalized[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Returns the int value at $key, or null when absent or not an int.
     *
     * @param array<string, mixed> $normalized
     */
    private function redactedIntOrNull(array $normalized, string $key): ?int
    {
        $value = $normalized[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
