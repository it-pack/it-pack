<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * Decorates a {@see ConfigurationResolverInterface} with opt-in artifact caching.
 *
 * On every {@see self::resolve()} it derives a content hash from the effective
 * sources + schema + pipeline + context (via {@see ConfigurationCompiler}) and
 * asks the {@see ConfigurationCacheInterface} for a match. A hit returns the
 * reconstructed immutable snapshot without touching sources/processors; a miss
 * (or any cache failure) resolves normally and stores the fresh artifact for the
 * next boot. The caching layer is invisible to callers — identical snapshots,
 * identical failure modes — and is only active when the builder opted in via
 * {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder::withCache()}.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §7 "Compiled / Cached Artifacts"
 */
final class CachingResolver implements ConfigurationResolverInterface
{
    /**
     * @param list<ConfigurationSourceInterface>      $defaultSources
     * @param list<ConfigurationProcessorInterface>   $processors
     * @param list<ConfigurationReaderInterface>      $readers
     * @param list<ConfigurationLoaderInterface>      $loaders
     */
    public function __construct(
        private ConfigurationResolverInterface $inner,
        private ConfigurationCacheInterface    $cache,
        private ConfigurationCompiler          $compiler,
        private array                          $defaultSources,
        private ?ConfigurationSchemaInterface  $schema,
        private array                          $processors,
        private array                          $readers,
        private array                          $loaders,
        private ?ExecutionContextInterface      $executionContext,
    ) {
    }

    public function resolve(iterable $sources = []): ConfigurationSnapshotInterface
    {
        $effective = $sources === [] ? $this->defaultSources : $sources;

        $key = $this->compiler->hash(
            $effective,
            $this->schema,
            $this->processors,
            $this->readers,
            $this->loaders,
            $this->executionContext,
        );

        $cached = $this->cache->get($key);

        if ($cached instanceof ConfigurationSnapshotInterface) {
            return $cached;
        }

        $snapshot = $this->inner->resolve($sources);

        // Writes are best-effort: a failing cache must never break resolution.
        try {
            $this->cache->set($key, $snapshot);
        } catch (\Throwable) {
            // Ignore — the live snapshot is still returned to the caller.
        }

        return $snapshot;
    }
}
