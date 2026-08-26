<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Cache;

use ItPack\ConfigureIt\Contract\RemoteDocumentCacheInterface;

/**
 * Filesystem-backed raw-document cache for remote configuration backends.
 *
 * Mirrors the opcache-friendly, `include`/`var_export` storage mechanism of
 * {@see FileSystemCache} (M3 compiled artifacts) but stores the raw fetched
 * document together with an optional expiry timestamp, so a
 * {@see \ItPack\ConfigureIt\Adapter\CachingReader} can honour a TTL and still
 * fall back to a stale-but-present last-good copy on fetch failure. A missing,
 * unreadable or corrupt entry yields null so the reader falls back to a live
 * fetch. Writes are best-effort and land atomically (temp file + rename); the
 * directory is created group-writable at most ({@see self::DEFAULT_DIRECTORY_MODE}).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final class FileSystemDocumentCache implements RemoteDocumentCacheInterface
{
    /** Cached documents may embed secrets: never world-readable. */
    private const int DEFAULT_DIRECTORY_MODE = 0o770;

    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, self::DEFAULT_DIRECTORY_MODE, true);
        }
    }

    public function get(string $key): ?string
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $data = @include $file;

        if (!is_array($data) || !isset($data['document']) || !isset($data['expires'])) {
            return null;
        }

        $document = $data['document'];

        if (!is_string($document)) {
            return null;
        }

        $expires = $data['expires'];

        if (is_int($expires) && $expires < time()) {
            return null;
        }

        return $document;
    }

    public function getLastGood(string $key): ?string
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $data = @include $file;

        if (!is_array($data) || !isset($data['document'])) {
            return null;
        }

        $document = $data['document'];

        return is_string($document) ? $document : null;
    }

    public function set(string $key, string $document, ?int $ttlSeconds = null): void
    {
        $file = $this->path($key);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));

        $payload = [
            'expires' => $ttlSeconds === null ? null : time() + $ttlSeconds,
            'document' => $document,
        ];

        try {
            file_put_contents($tmp, '<?php return ' . var_export($payload, true) . ';');
            rename($tmp, $file);
        } catch (\Throwable) {
            // Best-effort: a failed write must not break resolution.
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function path(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);

        return rtrim($this->directory, '/\\') . '/' . $safe . '.php';
    }
}
