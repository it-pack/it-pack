<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection;

use _Fixture\Stubs;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Reflection\WeakReflectionCache;
use ReflectionClass;

class WeakReflectionCacheTest extends Unit
{
    public function testItImplementsTheCacheContract(): void
    {
        self::assertInstanceOf(ReflectionCacheInterface::class, new WeakReflectionCache());
    }

    public function testGetReturnsTheStoredReflection(): void
    {
        $reflection = new ReflectionClass(Stubs\ClassA::class);
        $cache = new WeakReflectionCache();
        $cache->set(Stubs\ClassA::class, $reflection);

        self::assertSame($reflection, $cache->get(Stubs\ClassA::class));
    }

    public function testUnknownKeyReturnsNull(): void
    {
        self::assertNull(new WeakReflectionCache()->get(Stubs\ClassA::class));
    }

    public function testHeldReflectionIsNotKeptAlive(): void
    {
        $cache = new WeakReflectionCache();
        $cache->set(Stubs\ClassA::class, new ReflectionClass(Stubs\ClassA::class));

        // The only reference is the WeakReference; after a collection cycle the
        // cached reflection must be silently dropped rather than leaked.
        gc_collect_cycles();

        self::assertNull($cache->get(Stubs\ClassA::class));
    }
}
