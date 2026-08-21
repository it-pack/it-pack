<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Port for storing and restoring a frozen configuration artifact.
 *
 * Cache implementations are infrastructure (filesystem, APCu, Redis) and live
 * behind this port so the Core never depends on a storage backend. An artifact
 * is addressed by a content-derived key produced by the
 * {@see \ItPack\ConfigureIt\Core\ConfigurationCompiler}; a cache miss (or an
 * unreadable/corrupt artifact) must return null so the caller falls back to a
 * live resolution.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §7 "Compiled / Cached Artifacts"
 */
interface ConfigurationCacheInterface
{
    /**
     * Return the cached snapshot for the given key, or null on miss / failure.
     */
    public function get(string $key): ?ConfigurationSnapshotInterface;

    /**
     * Persist a resolved snapshot under the given key. Implementations must be
     * best-effort: a write failure must not break the resolution flow.
     */
    public function set(string $key, ConfigurationSnapshotInterface $snapshot): void;
}
