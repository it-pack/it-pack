<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Registry of versioned JSON-Schema descriptors for telemetry artifacts.
 *
 * Holds occurrence, measurement, and observation schemas keyed by type and
 * monotonic version. Consumers look up a schema to validate scheduler
 * artifacts before publication into the RunIt artifact stream.
 *
 * @see https://w3id.org/itpack/asyncit#TelemetrySchemaRegistry
 * @see https://w3id.org/itpack/asyncit#TelemetrySchema
 * @see https://w3id.org/itpack/asyncit-taxonomy#schema-occurrence
 * @see https://w3id.org/itpack/asyncit-taxonomy#schema-measurement
 * @see https://w3id.org/itpack/asyncit-taxonomy#schema-observation
 * @api
 */
interface TelemetrySchemaRegistryInterface
{
    /**
     * Registers a JSON-Schema descriptor for a telemetry type at a version.
     *
     * @param string $type Telemetry type (occurrence, measurement, observation)
     * @param int $version Monotonic schema version (starts at 1)
     * @param array<string, mixed> $schema JSON-Schema descriptor
     */
    public function register(string $type, int $version, array $schema): void;

    /**
     * Returns the schema for the given type and version, or null if absent.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $type, int $version): ?array;

    /**
     * Returns the latest (highest version) schema for the given type, or null.
     *
     * @return array<string, mixed>|null
     */
    public function latest(string $type): ?array;

    public function has(string $type, int $version): bool;

    /**
     * @return list<string> Registered telemetry types.
     */
    public function types(): array;
}
