<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
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
        ?DeriveEventSinkInterface      $eventSink = null,
    ) {
        parent::__construct($signatureFactory, $config, $factory, $reflectionCache ?? new InMemoryReflectionCache(), $eventSink);
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
        $totalTraitMethods = 0;
        $adaptee = ['adaptee' => $reflection->getName()];

        $allowedNamespaces = LeakageAuditor::allowedNamespaces(
            $reflection->getNamespaceName(),
            $this->config->typeSubstitutionMap,
            [],
        );

        $this->events->activityStarted('graph.walk', $adaptee);
        $traits = ClassReflection::create($reflection->getName())->getTraitsRecursively();
        $this->events->activityFinished('graph.walk');
        $this->events->measured('family.members', count($traits), 'count', ['graph' => 'traits']);

        $this->events->activityStarted('surface.extract', $adaptee + ['surface' => 'trait-interfaces']);
        foreach ($traits as $trait) {
            $shortName = Naming::interfaceShortName($trait->getShortName());
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
                if (isset($claimed[$name])) {
                    continue;
                }
                $signature = $this->signatureFor($method, $substitutor);
                if ($signature === null) {
                    continue;
                }
                $claimed[$name] = true;
                $methods[] = $signature;
            }

            if ($methods === []) {
                continue;
            }

            $interface = new InterfaceType($shortName);
            Naming::assertMethodNamesUnique($interface->getName(), $methods);
            $interface->setMethods($methods);

            $this->events->activityStarted('leakage.audit', ['target' => $trait->getName()]);
            new LeakageAuditor()->audit(
                $interface,
                $trait->getNamespaceName(),
                $allowedNamespaces,
                $this->config->auditLeakage,
            );
            $this->events->activityFinished('leakage.audit');

            $interfaces[$trait->getShortName()] = $interface;
            $totalTraitMethods += count($methods);
        }
        $this->events->activityFinished('surface.extract');
        $this->events->measured('methods.derived', $totalTraitMethods, 'count', $adaptee + ['surface' => 'trait-interfaces']);

        return $interfaces;
    }

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        $reflection = $this->reflect($class);
        Naming::assertNoCollision($reflection);
        $this->events->occurred('derivation.started', ['target' => $reflection->getName()]);
        $main = $this->assemble($reflection, $this->deriveTraitInterfaces($reflection));
        $this->events->occurred('derivation.completed', ['target' => $reflection->getName()]);

        return $main;
    }

    /**
     * Composes the main interface around already-derived trait interfaces.
     * Split from {@see self::derive()} so {@see self::deriveSource()} can derive
     * the trait graph exactly once and still emit each pipeline stage's events
     * exactly once.
     *
     * @param array<string, InterfaceType> $traitInterfaces
     */
    private function assemble(ReflectionClass $reflection, array $traitInterfaces): InterfaceType
    {
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
        $adaptee = ['adaptee' => $reflection->getName()];

        $mainMethods = [];
        $this->events->activityStarted('surface.extract', $adaptee + ['surface' => 'main']);
        foreach ($this->extractor->extract($reflection) as $method) {
            if (isset($traitMethodNames[$method->getName()])) {
                continue;
            }
            $signature = $this->signatureFor($method, $substitutor);
            if ($signature === null) {
                continue;
            }
            $mainMethods[] = $signature;
        }
        $this->events->activityFinished('surface.extract');
        $this->events->measured('methods.derived', count($mainMethods), 'count', $adaptee + ['surface' => 'main']);

        Naming::assertMethodNamesUnique($main->getName(), $mainMethods);
        $main->setMethods($mainMethods);

        $this->events->activityStarted('leakage.audit', $adaptee);
        new LeakageAuditor()->audit(
            $main,
            $reflection->getNamespaceName(),
            LeakageAuditor::allowedNamespaces($reflection->getNamespaceName(), $this->config->typeSubstitutionMap, []),
            $this->config->auditLeakage,
        );
        $this->events->activityFinished('leakage.audit');

        self::assertPerTraitSelfContained($main, $traitInterfaces);

        return $main;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = $this->reflect($class);
        Naming::assertNoCollision($reflection);
        $this->events->occurred('derivation.started', ['target' => $reflection->getName()]);

        // Derive the trait graph exactly once and reuse it for both the composed
        // main interface and the rendered output (REVIEW_3 §4 DRY note; telemetry
        // stages then appear exactly once per phase).
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);
        $main = $this->assemble($reflection, $traitInterfaces);

        $source = $this->renderer->renderMany([...array_values($traitInterfaces), $main], $reflection->getNamespaceName());
        $this->events->measured('source.bytes', strlen($source), 'By', ['target' => $reflection->getName()]);
        $this->events->occurred('derivation.completed', ['target' => $reflection->getName()]);

        return $source;
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
