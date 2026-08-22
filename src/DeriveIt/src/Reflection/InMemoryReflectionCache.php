<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Reflection;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ReflectionClass;

/**
 * Default in-process {@see ReflectionCacheInterface}: memoizes {@see ReflectionClass}
 * instances in an instance field so a single deriver / family scan reflects each
 * class at most once (G18 / E5). Instance-scoped, never static.
 */
final class InMemoryReflectionCache implements ReflectionCacheInterface
{
    /** @var array<string, ReflectionClass> */
    private array $items = [];

    public function get(string $fqcn): ?ReflectionClass
    {
        return $this->items[$fqcn] ?? null;
    }

    public function set(string $fqcn, ReflectionClass $reflection): void
    {
        $this->items[$fqcn] = $reflection;
    }
}
