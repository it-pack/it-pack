<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ReflectionClass;

/**
 * Spy cache counting get/set calls so the memoization contract is observable
 * (R4-7 / F-5 — extracted from Phase15BuildMetaAndCacheTest).
 */
final class CountingReflectionCache implements ReflectionCacheInterface
{
    /** @var list<string> */
    public array $gets = [];
    /** @var list<string> */
    public array $sets = [];

    private array $items = [];

    public function get(string $fqcn): ?ReflectionClass
    {
        $this->gets[] = $fqcn;

        return $this->items[$fqcn] ?? null;
    }

    public function set(string $fqcn, ReflectionClass $reflection): void
    {
        $this->sets[] = $fqcn;
        $this->items[$fqcn] = $reflection;
    }
}
