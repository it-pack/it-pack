<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\SemConv;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\Contract\SemConv\SemConvStoreInterface;

/**
 * Filesystem-backed SemConvStoreInterface. Loads/saves the canonical
 * name→value registry source at a single file path. This is the ONE place
 * DisenchantIt is allowed to touch the filesystem for SemConv persistence —
 * it is an adapter (not Core), and Core depends only on the
 * {@see SemConvStoreInterface} port, so the store can be swapped or stubbed in
 * tests.
 *
 * Save is fail-closed: a write failure throws rather than silently losing the
 * accumulated registry.
 */
final class FileSemConvStore implements SemConvStoreInterface
{
    public function __construct(
        private string $path,
    ) {
    }

    public function load(): ?string
    {
        if (!is_file($this->path)) {
            return null;
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read SemConv store: {$this->path}");
        }

        return $contents === '' ? null : $contents;
    }

    public function save(string $source): void
    {
        $dir = dirname($this->path);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create SemConv store directory: {$dir}");
            }
        }

        $written = file_put_contents($this->path, $source);
        if ($written === false) {
            throw new \RuntimeException("Failed to write SemConv store: {$this->path}");
        }
    }
}
