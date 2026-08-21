<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\RemoteDocumentCacheInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;

/**
 * Reader decorator that adds resilience to any remote-capable reader.
 *
 * On each read it first consults the {@see RemoteDocumentCacheInterface}:
 *   - a fresh (unexpired) entry is served without touching the network;
 *   - on a cache miss or stale entry it delegates to the inner reader, then
 *     stores the result for the configured TTL;
 *   - if the inner reader throws, the decorator serves the last-good cached
 *     document (even if expired) so a transient backend outage never takes down
 *     configuration resolution. When no last-good copy exists, the original
 *     exception propagates.
 *
 * This is the canonical "cache processor" the roadmap anticipated for remote
 * sources (Plan §6) and reuses the M3 cache mechanism via
 * {@see RemoteDocumentCacheInterface}.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class CachingReader implements ConfigurationReaderInterface
{
    public function __construct(
        private ConfigurationReaderInterface $inner,
        private RemoteDocumentCacheInterface $cache,
        private int                          $ttlSeconds = 60,
    ) {
    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        if (!$this->supports($resource)) {
            throw new ReadException('CachingReader cannot read resource "' . $resource->uri() . '".');
        }

        $key = $this->key($resource);

        $fresh = $this->cache->get($key);

        if ($fresh !== null) {
            return $fresh;
        }

        try {
            $document = $this->inner->read($resource);
        } catch (ReadException $e) {
            $lastGood = $this->cache->getLastGood($key);

            if ($lastGood !== null) {
                return $lastGood;
            }

            throw $e;
        }

        $this->cache->set($key, $document, $this->ttlSeconds);

        return $document;
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        return $this->inner->supports($resource);
    }

    private function key(ConfigurationResourceInterface $resource): string
    {
        return $resource->format()->name . ':' . $resource->uri();
    }
}
