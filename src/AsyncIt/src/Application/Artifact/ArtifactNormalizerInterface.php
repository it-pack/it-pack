<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

/**
 * Normalizes scheduler artifacts into version-tagged, schema-conformant
 * arrays.
 *
 * Backs {@see ArtifactNormalizer} so artifact normalization remains mockable
 * and interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @api
 */
interface ArtifactNormalizerInterface
{
    /**
     * Normalizes a raw artifact payload into a version-tagged, schema-conformant
     * array.
     *
     * @param string $type Telemetry type (occurrence, measurement, observation)
     * @param int $version Monotonic schema version
     * @param array<string, mixed> $artifact Raw artifact payload
     * @return array<string, mixed>
     */
    public function normalize(string $type, int $version, array $artifact): array;
}
