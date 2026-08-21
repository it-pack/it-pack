<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Port for caching the raw document fetched from a remote configuration
 * backend (Consul, etcd, custom HTTP endpoint, Vault, …).
 *
 * This is the reader-level sibling of {@see ConfigurationCacheInterface} (M3):
 * where the M3 cache stores fully-resolved immutable *snapshots* keyed by a
 * content hash, this cache stores the *raw fetched document* keyed by resource
 * URI so a {@see \ItPack\ConfigureIt\Adapter\CachingReader} can serve it on a
 * TTL and, crucially, fall back to the last-good copy when a live fetch fails
 * (resilience). Implementations are infrastructure and live behind this port
 * (ADR-0002) — the Core never depends on a storage backend.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
interface RemoteDocumentCacheInterface
{
    /**
     * Return the cached raw document for the given key, or null on miss or when
     * the cached entry has expired (TTL honouring).
     */
    public function get(string $key): ?string;

    /**
     * Return the last stored raw document for the given key regardless of TTL
     * expiry. Used by the {@see \ItPack\ConfigureIt\Adapter\CachingReader} as a
     * fallback when a live fetch throws — the entry may be stale, but stale is
     * better than no configuration at all.
     */
    public function getLastGood(string $key): ?string;

    /**
     * Persist a raw document under the given key. Passing null for $ttlSeconds
     * means "never expires" (serve until explicitly invalidated). Writes must be
     * best-effort: a failure must not break the resolution flow.
     */
    public function set(string $key, string $document, ?int $ttlSeconds = null): void;
}
