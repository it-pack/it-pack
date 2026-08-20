<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

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
 */
final class FamilyDeriver
{
    /**
     * @var array<string, InterfaceType> memo cache keyed by class FQCN + config hash.
     * Instance-scoped (not static) so it cannot leak across unrelated derivations
     * or mask Infection mutants — see G4 / EC3.
     */
    private array $cache = [];

    public function __construct(
        private readonly DeriveConfig $config = new DeriveConfig(),
    ) {
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function derive(array $classes, array $namespacePrefixMap = []): array
    {
        $map = $this->buildMap($classes);
        $map = $this->transitiveClosure($map);
        $configHash = $this->configHash($map, $namespacePrefixMap);

        // Each base interface is derived exactly once and memoized per config
        // (E1 / E2); the owned-namespace prefix is threaded through the substitutor.
        $result = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $fqcn = $reflection->getName();
            $cacheKey = $fqcn . '|' . $configHash;

            $result[$fqcn] = $this->cache[$cacheKey] ??= $this->deriveMember($reflection, $map, $namespacePrefixMap);
        }

        return $result;
    }

    /**
     * Auto-discovers the family reachable from a single root class and derives it.
     *
     * @param array<string, string> $namespacePrefixMap
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function deriveFromRoot(string $rootClass, array $namespacePrefixMap = []): array
    {
        $classes = array_keys($this->discover($rootClass));

        return $this->derive($classes, $namespacePrefixMap);
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
        $interfaces = $this->derive($classes, $namespacePrefixMap);

        $byNamespace = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
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
            signatureFactory: new MethodSignatureFactory(config: $memberConfig),
            config: $memberConfig,
        )->deriveWithSubstitutor($reflection, $substitutor, $allowedNamespaces);
    }

    /**
     * @param array<int, class-string> $classes
     * @return array<string, string>
     */
    private function buildMap(array $classes): array
    {
        $map = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $map[$reflection->getName()] = Naming::derivedInterfaceFqcn($reflection);
        }

        return $map;
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
     */
    private function discover(string $rootClass): array
    {
        $seen = [];
        $queue = [new ReflectionClass($rootClass)];

        while ($queue !== []) {
            $reflection = array_shift($queue);
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
                        if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                            $queue[] = new ReflectionClass($className);
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

        /** @var ReflectionNamedType $namedType */
        $namedType = $type;

        return [$namedType];
    }

    private function isUserland(string $className): bool
    {
        if (!str_contains($className, '\\')) {
            return false;
        }

        foreach (['Nette\\', 'PHPUnit\\', 'Codeception\\', 'Symfony\\', 'Composer\\', 'Illuminate\\'] as $vendor) {
            if (str_starts_with($className, $vendor)) {
                return false;
            }
        }

        return true;
    }
}
