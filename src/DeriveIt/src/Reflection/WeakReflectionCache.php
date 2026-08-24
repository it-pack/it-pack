<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Reflection;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ReflectionClass;
use WeakReference;

/**
 * Alternative {@see ReflectionCacheInterface} (F-7 / RP-7) that holds each
 * {@see ReflectionClass} by {@see WeakReference}, so the cache never keeps a type
 * alive on its own. Useful for long-running scans (e.g. a whole-repo family
 * pass) where the in-memory cache would otherwise retain every reflected class
 * for the process lifetime. `InMemoryReflectionCache` remains the default.
 */
final class WeakReflectionCache implements ReflectionCacheInterface
{
    /** @var array<string, WeakReference<ReflectionClass>> */
    private array $items = [];

    public function get(string $fqcn): ?ReflectionClass
    {
        $ref = $this->items[$fqcn] ?? null;

        return $ref !== null ? ($ref->get() ?? null) : null;
    }

    public function set(string $fqcn, ReflectionClass $reflection): void
    {
        $this->items[$fqcn] = WeakReference::create($reflection);
    }
}
