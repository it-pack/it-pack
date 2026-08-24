<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\FamilyDeriverInterface;
use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Derives a whole family of classes together so that types referencing one another
 * are rewritten to the derived interface FQCNs (Phase 4.2 batch auto-detection).
 *
 * Phase 9 hardening (C1 / C2 / C3 / C4 / E1 / E2):
 *  - transitive map closure so chained substitutions resolve fully (C1);
 *  - owned-namespace prefix rewriting for any referenced userland type (C2);
 *  - auto-discovery of the family from a single root class's type graph (C3);
 *  - a single combined source string grouping all interfaces by namespace (C4);
 *  - each base interface is derived exactly once and memoized per config (E1 / E2).
 *
 * Phase 10.15 (G18 / E5): an injectable {@see ReflectionCacheInterface} memoizes
 * each {@see ReflectionClass} so a whole-repo family scan reflects every type at
 * most once and never re-runs `class_exists`.
 */
final class FamilyDeriver implements FamilyDeriverInterface
{
    /**
     * Vendor/third-party namespaces that are never treated as userland types when
     * auto-discovering a family (so the scan doesn't try to reflect framework code).
     */
    private const VENDOR_PREFIXES = [
        'Nette\\',
        'PHPUnit\\',
        'Codeception\\',
        'Symfony\\',
        'Composer\\',
        'Illuminate\\',
    ];
    /**
     * @var array<string, InterfaceType> memo cache keyed by class FQCN + config hash.
     * Instance-scoped (not static) so it cannot leak across unrelated derivations
     * or mask Infection mutants — see G4 / EC3.
     */
    private array $cache = [];

    public function __construct(
        private readonly DeriveConfig            $config = new DeriveConfig(),
        private readonly ?Factory                $factory = null,
        private readonly ReflectionCacheInterface $reflectionCache = new InMemoryReflectionCache(),
    ) {
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Reflects a class name, memoizing the {@see ReflectionClass} in the injectable
     * cache so a whole-repo scan never reflects (or `class_exists`) a type twice
     * (G18 / E5).
     */
    private function reflect(string $fqcn): ReflectionClass
    {
        $cached = $this->reflectionCache->get($fqcn);
        if ($cached !== null) {
            return $cached;
        }

        $reflection = new ReflectionClass($fqcn);
        $this->reflectionCache->set($fqcn, $reflection);

        return $reflection;
    }

    /**
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function derive(array $classes, array $namespacePrefixMap = []): array
    {
        $reflections = [];
        foreach ($classes as $class) {
            $reflections[$class] = $this->reflect($class);
        }

        return $this->deriveReflections($reflections, $namespacePrefixMap);
    }

    /**
     * Auto-discovers the family reachable from a single root class and derives it.
     *
     * @param array<string, string> $namespacePrefixMap
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function deriveFromRoot(string $rootClass, array $namespacePrefixMap = []): array
    {
        // `discover()` already builds a ReflectionClass per family member, so reuse
        // those instances — don't re-reflect or re-run `class_exists` (EC20 / G13).
        return $this->deriveReflections($this->discover($rootClass), $namespacePrefixMap);
    }

    /**
     * Derives a family and returns a single source string containing every derived
     * interface, grouped by namespace (C4).
     *
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap
     */
    public function deriveSource(array $classes, array $namespacePrefixMap = []): string
    {
        $reflections = [];
        foreach ($classes as $class) {
            $reflections[$class] = $this->reflect($class);
        }
        $interfaces = $this->deriveReflections($reflections, $namespacePrefixMap);

        $byNamespace = [];
        foreach ($reflections as $reflection) {
            $byNamespace[$reflection->getNamespaceName()][] = $interfaces[$reflection->getName()];
        }

        $file = new PhpFile();
        foreach ($byNamespace as $namespace => $members) {
            $ns = new PhpNamespace($namespace);
            foreach ($members as $member) {
                $ns->add($member);
            }
            $file->addNamespace($ns);
        }

        return (string) $file;
    }

    /**
     * @param array<string, string> $map
     * @param array<string, string> $namespacePrefixMap
     */
    /**
     * Core family derivation over already-built ReflectionClass instances so that
     * discovery / derive never re-reflect the same class or re-run `class_exists`
     * (EC20 / G13). Each base interface is derived exactly once and memoized per
     * config (E1 / E2); a single `Factory` is shared across every member.
     *
     * @param array<class-string, ReflectionClass> $reflections keyed by class FQCN
     * @param array<string, string> $namespacePrefixMap
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    private function deriveReflections(array $reflections, array $namespacePrefixMap): array
    {
        $map = [];
        foreach ($reflections as $reflection) {
            $map[$reflection->getName()] = Naming::derivedInterfaceFqcn($reflection);
        }
        $map = $this->transitiveClosure($map);
        $configHash = $this->configHash($map, $namespacePrefixMap);

        $result = [];
        foreach ($reflections as $reflection) {
            $fqcn = $reflection->getName();
            $cacheKey = $fqcn . '|' . $configHash;

            $result[$fqcn] = $this->cache[$cacheKey] ??= $this->deriveMember($reflection, $map, $namespacePrefixMap);
        }

        return $result;
    }

    private function deriveMember(ReflectionClass $reflection, array $map, array $namespacePrefixMap): InterfaceType
    {
        Naming::assertNoCollision($reflection);

        $selfFqcn = Naming::derivedInterfaceFqcn($reflection);
        $parent = $reflection->getParentClass();
        $parentFqcn = $parent !== false ? Naming::derivedInterfaceFqcn($parent) : null;

        $substitutor = new TypeSubstitutor($map, $selfFqcn, $parentFqcn, $namespacePrefixMap);

        $allowedNamespaces = LeakageAuditor::allowedNamespaces($reflection->getNamespaceName(), $map, $namespacePrefixMap);

        $memberConfig = $this->config->withTypeSubstitutionMap($map);

        return new InterfaceDeriver(
            signatureFactory: new MethodSignatureFactory(factory: $this->factory, config: $memberConfig),
            config: $memberConfig,
        )->deriveWithSubstitutor($reflection, $substitutor, $allowedNamespaces);
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    private function transitiveClosure(array $map): array
    {
        $closure = $map;
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($closure as $key => $value) {
                if (isset($closure[$value]) && $closure[$value] !== $value) {
                    $closure[$key] = $closure[$value];
                    $changed = true;
                }
            }
        }

        return $closure;
    }

    /**
     * @param array<string, string> $map
     * @param array<string, string> $namespacePrefixMap
     */
    private function configHash(array $map, array $namespacePrefixMap): string
    {
        return md5(serialize($map) . '|' . serialize($namespacePrefixMap) . '|' . serialize($this->config));
    }

    /**
     * Walks the type graph reachable from a root class to discover the family.
     *
     * @return array<class-string, ReflectionClass>
     * @throws \ReflectionException
     */
    private function discover(string $rootClass): array
    {
        $seen = [];
        $queue = [$this->reflect($rootClass)];

        // Index-pointer walk (F-8 / RP-8): an index counter instead of repeated
        // `array_shift()` avoids re-indexing the whole queue on every dequeue
        // (O(n²) -> O(n) for large families). `count($queue)` is re-read each
        // iteration because members are appended during the walk.
        $i = 0;
        while ($i < count($queue)) {
            $reflection = $queue[$i++];
            $name = $reflection->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = $reflection;

            $parent = $reflection->getParentClass();
            if ($parent !== false) {
                $queue[] = $parent;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $types = [];
                $returnType = $method->getReturnType();
                if ($returnType !== null) {
                    $types[] = $returnType;
                }
                foreach ($method->getParameters() as $parameter) {
                    $parameterType = $parameter->getType();
                    if ($parameterType !== null) {
                        $types[] = $parameterType;
                    }
                }

                foreach ($types as $type) {
                    foreach ($this->namedTypes($type) as $named) {
                        if ($named->isBuiltin()) {
                            continue;
                        }
                        $className = $named->getName();
                        if (!$this->isUserland($className)) {
                            continue;
                        }
                        if (class_exists($className) || interface_exists($className) || trait_exists($className) || enum_exists($className)) {
                            $queue[] = $this->reflect($className);
                        }
                    }
                }
            }
        }

        return $seen;
    }

    /**
     * @return list<ReflectionNamedType>
     */
    private function namedTypes(ReflectionType $type): array
    {
        /** @var list<ReflectionNamedType> $named */
        $named = [];
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $inner) {
                $named = [...$named, ...$this->namedTypes($inner)];
            }

            return $named;
        }

        assert($type instanceof ReflectionNamedType);

        return [$type];
    }

    private function isUserland(string $className): bool
    {
        if (!str_contains($className, '\\')) {
            return false;
        }

        return array_all(
            self::VENDOR_PREFIXES,
            fn ($vendor) => !str_starts_with($className, $vendor)
        );
    }
}
