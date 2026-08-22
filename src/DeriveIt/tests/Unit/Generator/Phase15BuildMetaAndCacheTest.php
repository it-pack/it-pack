<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use _Fixture\Node;
use _Fixture\Tree;
use _Fixture\TypedConstantsClass;
use _Fixture\UsesTraitWithOverrideClass;
use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Generator\BuildMeta;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use ItPack\DeriveIt\Reflection\PsrCacheReflectionCache;
use Nette\PhpGenerator\InterfaceType;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;

/**
 * Step 10.15 (G15 / G18): `InterfaceDeriver::build()` returns a typed {@see BuildMeta}
 * value object; substitutor seeding + skip filtering live in the shared
 * {@see \ItPack\DeriveIt\Generator\AbstractDeriver} base (so flat + per-trait modes
 * stay in lockstep); and an injectable {@see ReflectionCacheInterface} memoizes
 * ReflectionClass instances so whole-repo scans never re-reflect a type.
 *
 * @covers \ItPack\DeriveIt\Generator\BuildMeta
 * @covers \ItPack\DeriveIt\Generator\AbstractDeriver
 * @covers \ItPack\DeriveIt\Generator\InterfaceDeriver
 * @covers \ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver
 * @covers \ItPack\DeriveIt\Reflection\InMemoryReflectionCache
 * @covers \ItPack\DeriveIt\Reflection\PsrCacheReflectionCache
 */
final class Phase15BuildMetaAndCacheTest extends TestCase
{
    public function testBuildMetaIsExposedThroughDerivationResult(): void
    {
        $result = new InterfaceDeriver(config: new DeriveConfig(includeConstants: true))
            ->deriveWithResult(TypedConstantsClass::class)
        ;

        self::assertInstanceOf(InterfaceType::class, $result->interface);
        self::assertContains('VERSION', $result->constants);
        self::assertSame(
            array_map(static fn (string $m): string => $m, array_keys($result->interface->getMethods())),
            $result->methods,
        );
        // Default config rewrites self/static to the derived interface FQCN.
        self::assertArrayHasKey('self', $result->substitutions);
        self::assertSame('_Fixture\TypedConstantsClassInterface', $result->substitutions['self']);
    }

    public function testSharedBaseAppliesSkipFilterInFlatMode(): void
    {
        $flat = new InterfaceDeriver()->derive(Node::class);
        $names = array_keys($flat->getMethods());
        self::assertNotEmpty($names);

        $keep = $names[0];
        $skip = $names[array_key_last($names)];

        $filtered = new InterfaceDeriver(config: new DeriveConfig(skipMethods: [$skip]))->derive(Node::class);

        self::assertArrayHasKey($keep, $filtered->getMethods());
        self::assertArrayNotHasKey($skip, $filtered->getMethods());
    }

    public function testSharedBaseAppliesSkipFilterInPerTraitMode(): void
    {
        // Per-trait mode: the main interface holds only methods not owned by any
        // trait, so assert the skip filter through the derived trait interfaces
        // (where the shared base's shouldSkipMethod is exercised per trait).
        $traitInterfaces = new PerTraitInterfaceDeriver()
            ->deriveTraitInterfaces(new ReflectionClass(UsesTraitWithOverrideClass::class))
        ;

        $allNames = [];
        foreach ($traitInterfaces as $iface) {
            $allNames = [...$allNames, ...array_keys($iface->getMethods())];
        }
        self::assertNotEmpty($allNames);

        $skip = $allNames[array_key_last($allNames)];
        $filtered = new PerTraitInterfaceDeriver(config: new DeriveConfig(skipMethods: [$skip]))
            ->deriveTraitInterfaces(new ReflectionClass(UsesTraitWithOverrideClass::class))
        ;

        $filteredNames = [];
        foreach ($filtered as $iface) {
            $filteredNames = [...$filteredNames, ...array_keys($iface->getMethods())];
        }
        self::assertNotContains($skip, $filteredNames);
    }

    public function testReflectionCacheMemoizesAcrossDerivations(): void
    {
        $cache = new CountingReflectionCache();
        $deriver = new InterfaceDeriver(reflectionCache: $cache);

        $deriver->derive(Node::class);
        $deriver->derive(Node::class); // same FQCN again — must reuse the cached ReflectionClass

        self::assertSame([Node::class], array_values(array_unique($cache->sets)));
        self::assertCount(1, $cache->sets);
        // The same FQCN must be reflected (get) at least twice but set only once.
        self::assertContains(Node::class, $cache->gets);
        self::assertGreaterThanOrEqual(2, count($cache->gets));
    }

    public function testFamilyScanReflectsEachTypeExactlyOnce(): void
    {
        $cache = new CountingReflectionCache();
        new FamilyDeriver(reflectionCache: $cache)->derive([Node::class, Tree::class]);

        $counts = array_count_values($cache->sets);
        foreach ($counts as $count) {
            self::assertSame(1, $count, 'Each FQCN must be reflected at most once per scan.');
        }
        self::assertContains(Node::class, $cache->sets);
        self::assertContains(Tree::class, $cache->sets);
    }

    public function testPsr6AdapterPersistsMarkerAndReusesInProcessObject(): void
    {
        $pool = new InMemoryPool();
        $adapter = new PsrCacheReflectionCache($pool);

        $reflection = new ReflectionClass(Node::class);
        $adapter->set(Node::class, $reflection);

        // Within the same process the heavy ReflectionClass is returned.
        self::assertSame($reflection, $adapter->get(Node::class));
        // The PSR-6 pool records that the FQCN was reflected (survives processes).
        self::assertTrue($pool->hasItem('deriveit.reflection.' . Node::class));

        // A fresh adapter over the same pool cannot reconstruct the (unserializable)
        // ReflectionClass, so get() returns null; only the marker persists.
        $fresh = new PsrCacheReflectionCache($pool);
        self::assertNull($fresh->get(Node::class));
    }
}

/**
 * Spy cache counting get/set calls so the memoization contract is observable.
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

/**
 * Minimal in-memory PSR-6 pool for adapter testing.
 */
final class InMemoryPool implements CacheItemPoolInterface
{
    /** @var array<string, CacheItemInterface> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? new CacheItem($key, hit: false);
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]) && $this->items[$key]->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = $item;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class CacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $hit = true,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
