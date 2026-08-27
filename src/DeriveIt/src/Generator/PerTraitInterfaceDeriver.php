<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEvents;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveMeasurements;
use ItPack\DeriveIt\Contract\Telemetry\DeriveUnits;
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
 * memoization live in the shared {@see DerivationCore} collaborator.
 */
final readonly class PerTraitInterfaceDeriver
{
    private readonly DerivationCore $core;

    public function __construct(
        DeriveConfig                   $config = new DeriveConfig(),
        ?Factory                       $factory = null,
        ?ReflectionCacheInterface      $reflectionCache = null,
        ?DeriveEventSinkInterface      $eventSink = null,
    ) {
        $this->core = DerivationCore::create(
            $config,
            $reflectionCache ?? new InMemoryReflectionCache(),
            $factory,
            $eventSink,
        );
    }

    /**
     * @return array<string, InterfaceType> keyed by trait short name.
     */
    public function deriveTraitInterfaces(ReflectionClass $reflection): array
    {
        $substitutor = $this->core->createTypeSubstitutor($reflection);

        $claimed = [];
        $interfaces = [];
        $shortNameToFqcn = [];
        $totalTraitMethods = 0;
        $adaptee = ['adaptee' => $reflection->getName()];

        $allowedNamespaces = LeakageAuditor::allowedNamespaces(
            $reflection->getNamespaceName(),
            $this->core->config()->typeSubstitutionMap,
            [],
        );

        $this->core->events()->activityStarted(DeriveEvents::ACTIVITY_GRAPH_WALK, $adaptee);
        $traits = ClassReflection::create($reflection->getName())->getTraitsRecursively();
        $this->core->events()->activityFinished(DeriveEvents::ACTIVITY_GRAPH_WALK);
        $this->core->events()->measured(
            DeriveMeasurements::MEASUREMENT_FAMILY_MEMBERS,
            count($traits),
            DeriveUnits::UNIT_COUNT,
            ['graph' => DeriveMeasurements::GRAPH_TRAITS],
        );

        $this->core->events()->activityStarted(
            DeriveEvents::ACTIVITY_SURFACE_EXTRACT,
            $adaptee + ['surface' => DeriveMeasurements::SURFACE_TRAIT_INTERFACES],
        );
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
            foreach ($this->core->extractor()->extract($trait) as $method) {
                $name = $method->getName();
                if (isset($claimed[$name])) {
                    continue;
                }
                $signature = $this->core->signatureFor($method, $substitutor);
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

            $this->core->events()->activityStarted(
                DeriveEvents::ACTIVITY_LEAKAGE_AUDIT,
                ['target' => $trait->getName()],
            );
            new LeakageAuditor()->audit(
                $interface,
                $trait->getNamespaceName(),
                $allowedNamespaces,
                $this->core->config()->auditLeakage,
            );
            $this->core->events()->activityFinished(DeriveEvents::ACTIVITY_LEAKAGE_AUDIT);

            $interfaces[$trait->getShortName()] = $interface;
            $totalTraitMethods += count($methods);
        }
        $this->core->events()->activityFinished(DeriveEvents::ACTIVITY_SURFACE_EXTRACT);
        $this->core->events()->measured(
            DeriveMeasurements::MEASUREMENT_METHODS_DERIVED,
            $totalTraitMethods,
            DeriveUnits::UNIT_COUNT,
            $adaptee + ['surface' => DeriveMeasurements::SURFACE_TRAIT_INTERFACES],
        );

        return $interfaces;
    }

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        $reflection = $this->core->reflect($class);
        Naming::assertNoCollision($reflection);
        $this->core->events()->occurred(DeriveEvents::EVENT_DERIVATION_STARTED, ['target' => $reflection->getName()]);
        $main = $this->assemble($reflection, $this->deriveTraitInterfaces($reflection));
        $this->core->events()->occurred(DeriveEvents::EVENT_DERIVATION_COMPLETED, ['target' => $reflection->getName()]);

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

        $substitutor = $this->core->createTypeSubstitutor($reflection);
        $adaptee = ['adaptee' => $reflection->getName()];

        $mainMethods = [];
        $this->core->events()->activityStarted(
            DeriveEvents::ACTIVITY_SURFACE_EXTRACT,
            $adaptee + ['surface' => DeriveMeasurements::SURFACE_MAIN],
        );
        foreach ($this->core->extractor()->extract($reflection) as $method) {
            if (isset($traitMethodNames[$method->getName()])) {
                continue;
            }
            $signature = $this->core->signatureFor($method, $substitutor);
            if ($signature === null) {
                continue;
            }
            $mainMethods[] = $signature;
        }
        $this->core->events()->activityFinished(DeriveEvents::ACTIVITY_SURFACE_EXTRACT);
        $this->core->events()->measured(
            DeriveMeasurements::MEASUREMENT_METHODS_DERIVED,
            count($mainMethods),
            DeriveUnits::UNIT_COUNT,
            $adaptee + ['surface' => DeriveMeasurements::SURFACE_MAIN],
        );

        Naming::assertMethodNamesUnique($main->getName(), $mainMethods);
        $main->setMethods($mainMethods);

        $this->core->events()->activityStarted(DeriveEvents::ACTIVITY_LEAKAGE_AUDIT, $adaptee);
        new LeakageAuditor()->audit(
            $main,
            $reflection->getNamespaceName(),
            LeakageAuditor::allowedNamespaces(
                $reflection->getNamespaceName(),
                $this->core->config()->typeSubstitutionMap,
                [],
            ),
            $this->core->config()->auditLeakage,
        );
        $this->core->events()->activityFinished(DeriveEvents::ACTIVITY_LEAKAGE_AUDIT);

        self::assertPerTraitSelfContained($main, $traitInterfaces);

        return $main;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = $this->core->reflect($class);
        Naming::assertNoCollision($reflection);
        $this->core->events()->occurred(DeriveEvents::EVENT_DERIVATION_STARTED, ['target' => $reflection->getName()]);

        // Derive the trait graph exactly once and reuse it for both the composed
        // main interface and the rendered output (REVIEW_3 §4 DRY note; telemetry
        // stages then appear exactly once per phase).
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);
        $main = $this->assemble($reflection, $traitInterfaces);

        $source = $this->core->renderer()->renderMany(
            [...array_values($traitInterfaces), $main],
            $reflection->getNamespaceName(),
        );
        $this->core->events()->measured(
            DeriveMeasurements::MEASUREMENT_SOURCE_BYTES,
            strlen($source),
            DeriveUnits::UNIT_BYTES,
            ['target' => $reflection->getName()],
        );
        $this->core->events()->occurred(DeriveEvents::EVENT_DERIVATION_COMPLETED, ['target' => $reflection->getName()]);

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
