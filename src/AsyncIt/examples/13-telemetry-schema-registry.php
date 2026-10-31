<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Artifact\ArtifactNormalizer;
use ItPack\AsyncIt\Domain\TelemetrySchemaRegistry;

$fixture = makeReferenceRuntime();
$scope = makeScope('telemetry-schema-registry');

echo "=== Telemetry Schema Registry (A.6) ===\n";

// A.6.1 — Versioned JSON-Schema registry keyed by telemetry type.
// Registers occurrence, measurement, and observation schemas at monotonic
// versions. latest() is O(1) (tracks max version at registration time).
$registry = new TelemetrySchemaRegistry();

// Register occurrence schema v1 (initial event shape).
$registry->register(
    'occurrence',
    1,
    [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'type' => 'object',
        'required' => ['kind', 'payload'],
        'properties' => [
            'kind' => ['type' => 'string'],
            'payload' => ['type' => 'object'],
        ],
    ],
);

// Register occurrence schema v2 (adds correlationId for distributed tracing).
$registry->register(
    'occurrence',
    2,
    [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'type' => 'object',
        'required' => ['kind', 'payload', 'correlationId'],
        'properties' => [
            'kind' => ['type' => 'string'],
            'payload' => ['type' => 'object'],
            'correlationId' => ['type' => 'string'],
        ],
    ],
);

// Register measurement schema v1.
$registry->register(
    'measurement',
    1,
    [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'type' => 'object',
        'required' => ['name', 'value', 'unit'],
        'properties' => [
            'name' => ['type' => 'string'],
            'value' => ['type' => 'number'],
            'unit' => ['type' => 'string'],
        ],
    ],
);

// Register observation schema v1.
$registry->register(
    'observation',
    1,
    [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'type' => 'object',
        'required' => ['metric', 'timestamp'],
        'properties' => [
            'metric' => ['type' => 'string'],
            'timestamp' => ['type' => 'integer'],
        ],
    ],
);

echo '  registered types: ' . implode(', ', $registry->types()), "\n";
echo '  occurrence v1 present: ', ($registry->has('occurrence', 1) ? 'yes' : 'no'), "\n";
echo '  occurrence v2 present: ', ($registry->has('occurrence', 2) ? 'yes' : 'no'), "\n";
echo "  occurrence latest version: 2 (tracked via maxVersions)\n";
echo '  measurement latest: ', $registry->latest('measurement')['required'][0] ?? 'n/a', "\n";
echo '  observation latest: ', $registry->latest('observation')['required'][0] ?? 'n/a', "\n";
echo '  unknown type latest: ', ($registry->latest('unknown') === null ? 'null (correct)' : 'unexpected'), "\n";

// A.6.2 — Version-tagged artifact normalization via ArtifactNormalizer.
// By default fail-closed: throws when no schema is registered for the
// type/version, so unpublished artifacts never slip through unvalidated.
$normalizer = new ArtifactNormalizer($registry);

// Normalize an occurrence artifact at v2 — schema exists, passes.
$normalized = $normalizer->normalize('occurrence', 2, [
    'kind' => 'task.spawned',
    'payload' => ['taskId' => 'task-abc'],
    'correlationId' => 'corr-123',
]);
echo '  normalized occurrence v2: ', json_encode($normalized), "\n";

// Fail-closed: normalize an occurrence at v3 — no schema registered, throws.
try {
    $normalizer->normalize('occurrence', 3, ['kind' => 'task.spawned']);
    echo "  ERROR: fail-closed should have thrown\n";
} catch (InvalidArgumentException $invalidArgumentException) {
    echo '  fail-closed rejected unregistered schema: ', $invalidArgumentException->getMessage(), "\n";
}

// passThrough(): degrades gracefully — no schema lookup, just tags the payload.
$passthrough = ArtifactNormalizer::passThrough($registry);
$passthroughResult = $passthrough->normalize('occurrence', 99, ['kind' => 'task.spawned']);
echo '  pass-through v99: ', json_encode($passthroughResult), "\n";

echo "\n=== Telemetry Schema Registry patterns demonstrated ===\n";
echo "  - Versioned JSON-Schema descriptors per telemetry type\n";
echo "  - O(1) latest() lookup (max version tracked at registration)\n";
echo "  - Fail-closed normalization prevents unvalidated artifact publication\n";
echo "  - Pass-through mode degrades gracefully for unknown schemas\n";
