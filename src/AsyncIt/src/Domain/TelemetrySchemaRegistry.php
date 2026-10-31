<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\TelemetrySchemaRegistryInterface;

/**
 * In-memory registry of versioned JSON-Schema descriptors for telemetry.
 *
 * Schemas are keyed by telemetry type (occurrence, measurement, observation)
 * and monotonic version. Registration is the only mutation path; lookups are
 * read-only. Consumers validate scheduler artifacts against the registered
 * schema before publication into the RunIt artifact stream.
 */
final class TelemetrySchemaRegistry implements TelemetrySchemaRegistryInterface
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $schemas = [];

    /**
     * @var array<string, int> Highest registered version per telemetry type.
     */
    private array $maxVersions = [];

    public function register(string $type, int $version, array $schema): void
    {
        if ($version < 1) {
            throw new InvalidArgumentException(
                'Schema version must be >= 1, ' . $version . ' given for type ' . $type . '.',
            );
        }

        $this->schemas[$type][$version] = $schema;

        if (!isset($this->maxVersions[$type]) || $version > $this->maxVersions[$type]) {
            $this->maxVersions[$type] = $version;
        }
    }

    public function get(string $type, int $version): ?array
    {
        return $this->schemas[$type][$version] ?? null;
    }

    public function latest(string $type): ?array
    {
        if (!isset($this->maxVersions[$type])) {
            return null;
        }

        return $this->schemas[$type][$this->maxVersions[$type]] ?? null;
    }

    public function has(string $type, int $version): bool
    {
        return isset($this->schemas[$type][$version]);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->schemas);
    }
}
