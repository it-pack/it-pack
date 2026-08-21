<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Cache;

use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Core\ConfigurationSnapshotFactory;
use ItPack\ConfigureIt\Core\TypedSnapshot;

/**
 * Filesystem-backed compiled-artifact cache (opt-in via the builder).
 *
 * Each artifact is a single opcache-friendly PHP file that `return`s the
 * serialized snapshot (tree + provenance, plus the typed-snapshot metadata so a
 * schema-backed cache entry is reconstructed as a {@see TypedSnapshot}). Reads
 * `include` the file; a missing, unreadable or corrupt artifact yields null so
 * the resolver transparently falls back to a live resolution. Writes are
 * best-effort and never interrupt resolution.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §7 "Compiled / Cached Artifacts"
 */
final class FileSystemCache implements ConfigurationCacheInterface
{
    public function __construct(
        private string                                   $directory,
        private ConfigurationSnapshotFactoryInterface $factory = new ConfigurationSnapshotFactory(),
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o777, true);
        }
    }

    public function get(string $key): ?ConfigurationSnapshotInterface
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $data = @include $file;

        if (!is_array($data) || !isset($data['snapshot'])) {
            return null;
        }

        try {
            $snapshot = $this->factory->buildFromArray((array) $data['snapshot']);
        } catch (\Throwable) {
            return null;
        }

        if (($data['typed'] ?? false) === true && isset($data['scalarTypes'])) {
            $scalarTypes = [];

            foreach ((array) $data['scalarTypes'] as $typeKey => $typeValue) {
                if (is_string($typeKey) && is_string($typeValue)) {
                    $scalarTypes[$typeKey] = $typeValue;
                }
            }

            return new TypedSnapshot($snapshot, $scalarTypes);
        }

        return $snapshot;
    }

    public function set(string $key, ConfigurationSnapshotInterface $snapshot): void
    {
        if ($snapshot instanceof TypedSnapshot) {
            $payload = [
                'typed'       => true,
                'scalarTypes' => $snapshot->scalarTypes(),
                'snapshot'    => $snapshot->innerSnapshot()->toArray(),
            ];
        } else {
            $payload = [
                'typed'       => false,
                'scalarTypes' => [],
                'snapshot'    => $snapshot instanceof ConfigurationSnapshot
                    ? $snapshot->toArray()
                    : ['tree' => $snapshot->all(), 'provenance' => []],
            ];
        }

        $file = $this->path($key);

        try {
            file_put_contents($file, '<?php return ' . var_export($payload, true) . ';');
        } catch (\Throwable) {
            // Best-effort: a failed write must not break resolution.
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\') . '/' . $key . '.php';
    }
}
