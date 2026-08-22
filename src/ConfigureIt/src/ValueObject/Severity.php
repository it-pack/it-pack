<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Severity of an {@see Observation} emitted during resolution.
 *
 * A closed vocabulary (info / warning / error) replacing the previously raw
 * `string $level` discriminator. Serialization boundaries use {@see self::value}
 * so persisted artifacts and telemetry stay string-compatible.
 */
enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    /**
     * @throws \InvalidArgumentException when the severity string is unknown.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf('Unknown severity "%s".', $value));
    }
}
