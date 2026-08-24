<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Reflection;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;

/**
 * PSR-6 ({@see CacheItemPoolInterface}) adapter for {@see ReflectionCacheInterface} (G18 / E5).
 *
 * `ReflectionClass` is not serializable, so the heavy reflection object is kept in
 * an in-process memory map for the life of the scan (and returned by {@see get()}
 * whenever available). The wrapped PSR-6 pool additionally persists a lightweight
 * marker recording that a given FQCN has been reflected, so a subsequent process
 * (e.g. a repeat whole-repo scan) knows the class was already discovered and can
 * skip its `class_exists` / re-discovery pass. Cross-process the object is rebuilt
 * on first access; within a process the same instance is reused.
 *
 * The pool is optional consumer infrastructure: this package depends only on the
 * `psr/cache` *interface* contract and never ships or requires an implementation —
 * bring your own (symfony/cache, doctrine/cache, …) and hand it to this adapter.
 */
final class PsrCacheReflectionCache implements ReflectionCacheInterface
{
    /**
     * Key prefix for the PSR-6 persisted "already reflected" markers (G18 / E5).
     */
    private const CACHE_KEY_PREFIX = 'deriveit.reflection.';

    /** @var array<string, ReflectionClass> */
    private array $memory = [];

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
    ) {
    }

    public function get(string $fqcn): ?ReflectionClass
    {
        return $this->memory[$fqcn] ?? null;
    }

    public function set(string $fqcn, ReflectionClass $reflection): void
    {
        $this->memory[$fqcn] = $reflection;

        $item = $this->pool->getItem($this->key($fqcn));
        $item->set(true);
        $this->pool->save($item);
    }

    private function key(string $fqcn): string
    {
        return self::CACHE_KEY_PREFIX . $fqcn;
    }
}
