<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\DeriverInterface;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Reflection\ClassReflection;
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
 */
final readonly class PerTraitInterfaceDeriver implements DeriverInterface
{
    private MethodExtractor $extractor;
    private SourceRenderer $renderer;
    private MethodSignatureFactory $signatureFactory;

    public function __construct(
        ?MethodSignatureFactory $signatureFactory = null,
        private DeriveConfig    $config = new DeriveConfig(),
    ) {
        $this->extractor = new MethodExtractor($config);
        $this->renderer = new SourceRenderer();
        $this->signatureFactory = $signatureFactory ?? new MethodSignatureFactory(config: $config);
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
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
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
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
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

        $shortName = static fn (string $fqcn): string => ($pos = strrpos($fqcn, '\\')) === false
            ? $fqcn
            : substr($fqcn, $pos + 1);
        $actual = array_map($shortName, $main->getExtends());
        sort($actual);

        if ($expected !== $actual) {
            throw new LeakageDetectedException(sprintf(
                'Main interface "%s" must extend exactly the derived trait interfaces (adaptee leakage).',
                $main->getName(),
            ));
        }
    }

    private function shouldSkipMethod(string $name): bool
    {
        if (in_array($name, $this->config->skipMethods, true)) {
            return true;
        }

        if ($this->config->onlyMethods !== [] && !in_array($name, $this->config->onlyMethods, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param ReflectionClass $reflection
     * @return TypeSubstitutor
     */
    private function createTypeSubstitutor(ReflectionClass $reflection): TypeSubstitutor
    {
        $selfFqcn = $this->config->substituteSelfStatic ? Naming::derivedInterfaceFqcn($reflection) : null;
        $parent = $reflection->getParentClass();
        $parentFqcn = ($this->config->substituteSelfStatic && $parent !== false)
            ? Naming::derivedInterfaceFqcn($parent)
            : null;

        return new TypeSubstitutor($this->config->typeSubstitutionMap, $selfFqcn, $parentFqcn);
    }
}
