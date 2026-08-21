<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

/**
 * The single place that decides how a configuration value is rendered for
 * human/telemetry consumption. When a value is {@see ProvenanceInterface::isSensitive()},
 * it is replaced with a fixed mask so secrets never leak into the inspector,
 * diffs, graphs, CLI output or telemetry (M4 — Epic E5.2).
 *
 * Non-sensitive values are formatted exactly as before (array → JSON, bool →
 * true/false, null → null, scalars → string), so behaviour is unchanged for
 * ordinary configuration.
 */
final class RedactingRenderer
{
    public function __construct(private string $mask = '****')
    {
    }

    public function mask(): string
    {
        return $this->mask;
    }

    public function render(mixed $value, bool $sensitive): string
    {
        if ($sensitive) {
            return $this->mask;
        }

        return $this->format($value);
    }

    private function format(mixed $value): string
    {
        if (\is_array($value)) {
            return (string) json_encode($value);
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
