<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Reflection\ClassReflection;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;

/**
 * Derives one interface per trait (Interface Segregation applied to the tool) and
 * composes the main interface so it `extends` exactly those derived trait
 * interfaces.
 *
 * Leakage invariant (per-trait mode): the main interface must extend ONLY the
 * derived trait interfaces — never the adaptee's own parent/interface hierarchy.
 * When a method name is declared by multiple traits, the first trait in
 * the getTraitsRecursively () order owns it; the others drop it, so no duplicate
 * method declaration can occur across the extended interfaces.
 *
 * Phase 10.15 (G15 / G18): substitutor seeding + skip filtering + reflection
 * memoization live in the shared {@see AbstractDeriver} base.
 */
final readonly class PerTraitInterfaceDeriver extends AbstractDeriver
{
    public function __construct(
        ?MethodSignatureFactory       $signatureFactory = null,
        DeriveConfig                   $config = new DeriveConfig(),
        ?Factory                       $factory = null,
        ?ReflectionCacheInterface      $reflectionCache = null,
    ) {
        parent::__construct($signatureFactory, $config, $factory, $reflectionCache ?? new InMemoryReflectionCache());
    }

    /**
     * @return array<string, InterfaceType> keyed by trait short name.
     */
    public function deriveTraitInterfaces(ReflectionClass $reflection): array
    {
        $substitutor = $this->createTypeSubstitutor($reflection);

        $claimed = [];
        $interfaces = [];
        $shortNameToFqcn = [];

        $allowedNamespaces = LeakageAuditor::allowedNamespaces(
            $reflection->getNamespaceName(),
            $this->config->typeSubstitutionMap,
            [],
        );

        foreach (ClassReflection::create($reflection->getName())->getTraitsRecursively() as $trait) {
            $shortName = $trait->getShortName() . 'Interface';
            $fqcn = Naming::derivedInterfaceFqcn($trait);

            // EC19: two traits sharing a short name in different namespaces would
            // silently overwrite each other — fail loudly instead.
            if (isset($shortNameToFqcn[$shortName]) && $shortNameToFqcn[$shortName] !== $fqcn) {
                throw NamingCollisionException::forFqcn($fqcn);
            }
            $shortNameToFqcn[$shortName] = $fqcn;

            Naming::assertNoCollision($trait);

            $methods = [];
            foreach ($this->extractor->extract($trait) as $method) {
                $name = $method->getName();
                if (isset($claimed[$name]) || $this->shouldSkipMethod($name)) {
                    continue;
                }
                $claimed[$name] = true;
                $signature = $this->signatureFactory->fromMethod($method, $substitutor);
                if (isset($this->config->methodRenames[$name])) {
                    $signature = $signature->cloneWithName($this->config->methodRenames[$name]);
                }
                $methods[] = $signature;
            }

            if ($methods === []) {
                continue;
            }

            $interface = new InterfaceType($shortName);
            Naming::assertMethodNamesUnique($interface->getName(), $methods);
            $interface->setMethods($methods);

            new LeakageAuditor()->audit(
                $interface,
                $trait->getNamespaceName(),
                $allowedNamespaces,
                $this->config->auditLeakage,
            );

            $interfaces[$trait->getShortName()] = $interface;
        }

        return $interfaces;
    }

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        $reflection = $this->reflect($class);
        Naming::assertNoCollision($reflection);
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);

        $main = new InterfaceType(Naming::derivedInterfaceShortName($reflection));
        $namespace = $reflection->getNamespaceName();
        $main->setExtends(array_map(
            static fn (InterfaceType $i): string => $namespace === ''
                ? $i->getName()
                : $namespace . '\\' . $i->getName(),
            array_values($traitInterfaces),
        ));

        $traitMethodNames = [];
        foreach ($traitInterfaces as $interface) {
            foreach ($interface->getMethods() as $method) {
                $traitMethodNames[$method->getName()] = true;
            }
        }

        $substitutor = $this->createTypeSubstitutor($reflection);

        $mainMethods = [];
        foreach ($this->extractor->extract($reflection) as $method) {
            $name = $method->getName();
            if (isset($traitMethodNames[$name]) || $this->shouldSkipMethod($name)) {
                continue;
            }
            $signature = $this->signatureFactory->fromMethod($method, $substitutor);
            if (isset($this->config->methodRenames[$name])) {
                $signature = $signature->cloneWithName($this->config->methodRenames[$name]);
            }
            $mainMethods[] = $signature;
        }
        Naming::assertMethodNamesUnique($main->getName(), $mainMethods);
        $main->setMethods($mainMethods);

        new LeakageAuditor()->audit(
            $main,
            $reflection->getNamespaceName(),
            LeakageAuditor::allowedNamespaces($reflection->getNamespaceName(), $this->config->typeSubstitutionMap, []),
            $this->config->auditLeakage,
        );

        self::assertPerTraitSelfContained($main, $traitInterfaces);

        return $main;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = $this->reflect($class);
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);
        $main = $this->derive($reflection);

        return $this->renderer->renderMany([...array_values($traitInterfaces), $main], $reflection->getNamespaceName());
    }

    /**
     * Asserts the core leakage invariant (per-trait mode): the main interface
     * extends exactly the derived trait interfaces.
     *
     * @param array<string, InterfaceType> $traitInterfaces
     */
    public static function assertPerTraitSelfContained(InterfaceType $main, array $traitInterfaces): void
    {
        $expected = array_map(static fn (InterfaceType $i): string => $i->getName(), array_values($traitInterfaces));
        sort($expected);

        $actual = array_map(static fn (string $fqcn): string => Naming::shortName($fqcn), $main->getExtends());
        sort($actual);

        if ($expected !== $actual) {
            throw new LeakageDetectedException(sprintf(
                'Main interface "%s" must extend exactly the derived trait interfaces (adaptee leakage).',
                $main->getName(),
            ));
        }
    }
}
