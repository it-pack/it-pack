<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port for loading and saving the canonical name → value map that backs the
 * shared SemConv registry. Implemented by {@see \ItPack\DisenchantIt\Adapter\FileSemConvStore}
 * (filesystem) so SemConvCollector can accumulate constants stably across runs
 * without Core ever touching the filesystem.
 *
 * Load returns null when no prior registry exists (first run); save persists
 * the merged, regenerated registry source.
 */
interface SemConvStoreInterface
{
    /**
     * @return string|null the persisted SemConv registry source, or null when
     *         none exists yet.
     */
    public function load(): ?string;

    public function save(string $source): void;
}
