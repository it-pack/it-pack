<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\TelemetrySchemaRegistryInterface;

/**
 * Normalizes scheduler artifacts into version-tagged, schema-conformant arrays.
 *
 * Wraps a raw artifact payload with its schema type and version so consumers
 * can validate against the {@see TelemetrySchemaRegistryInterface} before
 * publication into the RunIt artifact stream.
 *
 * By default, {@see self::normalize()} fails closed when no schema is
 * registered for the given type/version. Construct with
 * {@see self::passThrough()} to skip schema validation and pass artifacts
 * through unchanged when no schema exists, allowing telemetry publication to
 * degrade gracefully instead of breaking entirely.
 */
final readonly class ArtifactNormalizer implements ArtifactNormalizerInterface
{
    public function __construct(
        private TelemetrySchemaRegistryInterface $registry,
        private bool $failClosed = true,
    ) {
    }

    /**
     * Creates a normalizer that passes artifacts through without schema
     * validation when no schema is registered for the type/version.
     */
    public static function passThrough(TelemetrySchemaRegistryInterface $registry): self
    {
        return new self($registry, failClosed: false);
    }

    /**
     * Normalizes a raw artifact into a version-tagged, schema-conformant array.
     *
     * The returned array carries the schema type and version alongside the
     * original payload, enabling consumers to validate against the registry
     * before publication.
     *
     * When {@see $failClosed} is true (default), throws
     * {@see \InvalidArgumentException} for unregistered schemas. When false,
     * returns the payload wrapped in the version tag without schema lookup.
     *
     * @param string $type Telemetry type (occurrence, measurement, observation)
     * @param int $version Monotonic schema version
     * @param array<string, mixed> $artifact Raw artifact payload
     * @return array<string, mixed>
     */
    public function normalize(string $type, int $version, array $artifact): array
    {
        if ($this->failClosed && !$this->registry->has($type, $version)) {
            throw new InvalidArgumentException(
                'No schema registered for type "' . $type . '" version ' . $version . '.',
            );
        }

        return [
            'schemaType' => $type,
            'schemaVersion' => $version,
            'payload' => $artifact,
        ];
    }
}
