<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Cache;

use InvalidArgumentException;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Core\ConfigurationSnapshotFactory;
use ItPack\ConfigureIt\Core\TypedSnapshot;
use Throwable;

/**
 * Filesystem-backed compiled-artifact cache (opt-in via the builder).
 *
 * Each artifact is a single opcache-friendly PHP file that `return`s the
 * serialized snapshot (tree + provenance, plus the typed-snapshot metadata so a
 * schema-backed cache entry is reconstructed as a {@see TypedSnapshot}). Reads
 * `include` the file; a missing, unreadable or corrupt artifact yields null so
 * the resolver transparently falls back to a live resolution. Writes are
 * best-effort and never interrupt resolution; they land atomically (temp file +
 * rename) so readers never observe torn artifacts. Artifacts may embed
 * decrypted secrets, so the directory is created group-writable at most
 * ({@see self::DEFAULT_DIRECTORY_MODE}).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §7 "Compiled / Cached Artifacts"
 */
final class FileSystemCache implements ConfigurationCacheInterface
{
    /** Artifacts hold resolved-and-decrypted configuration: never world-readable. */
    private const int DEFAULT_DIRECTORY_MODE = 0o770;

    /**
     * Keys normally come from the compiler's hash; validation is
     * defense-in-depth so a hostile key cannot escape the cache directory.
     */
    private const string KEY_PATTERN = '/^[A-Za-z0-9._-]{1,128}$/';

    public function __construct(
        private string                                   $directory,
        private ConfigurationSnapshotFactoryInterface $factory = new ConfigurationSnapshotFactory(),
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, self::DEFAULT_DIRECTORY_MODE, true);
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
        } catch (Throwable) {
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
        $payload = $snapshot instanceof TypedSnapshot
            ? [
                'typed'       => true,
                'scalarTypes' => $snapshot->scalarTypes(),
                'snapshot'    => $snapshot->innerSnapshot()->toArray(),
            ]
            : [
                'typed'       => false,
                'scalarTypes' => [],
                'snapshot'    => $snapshot instanceof ConfigurationSnapshot
                    ? $snapshot->toArray()
                    : ['tree' => $snapshot->all(), 'provenance' => []],
            ];

        $file = $this->path($key);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));

        try {
            file_put_contents($tmp, '<?php return ' . var_export($payload, true) . ';');
            rename($tmp, $file);
        } catch (Throwable) {
            // Best-effort: a failed write must not break resolution.
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function path(string $key): string
    {
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(sprintf('Invalid cache key "%s".', $key));
        }

        return rtrim($this->directory, '/\\') . '/' . $key . '.php';
    }
}
