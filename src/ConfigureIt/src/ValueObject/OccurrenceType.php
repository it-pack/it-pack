<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Closed vocabulary of resolution-time {@see Occurrence} types.
 *
 * Replaces the previously raw `string $type` discriminator. Serialization
 * boundaries use {@see self::value} so persisted artifacts and telemetry stay
 * string-compatible.
 */
enum OccurrenceType: string
{
    case Loaded = 'ConfigurationLoaded';
    case Merged = 'ConfigurationMerged';
    case Validated = 'ConfigurationValidated';
    case Resolved = 'ConfigurationResolved';
    case SnapshotCreated = 'SnapshotCreated';
    case Drift = 'ConfigurationDrift';
    case Reloaded = 'ConfigurationReloaded';
    case Changed = 'ConfigurationChanged';

    /**
     * @throws \InvalidArgumentException when the occurrence type is unknown.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf('Unknown occurrence type "%s".', $value));
    }
}
