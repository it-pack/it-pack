<?php

declare(strict_types=1);

namespace ItPack\DeriveIt;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEvents;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Generator\AdapterGenerator;
use ItPack\DeriveIt\Generator\DerivationResult;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\DeriveDiff;
use ItPack\DeriveIt\Generator\Naming;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use ReflectionException;

/**
 * Public entry point for deriving self-contained interfaces from existing classes.
 *
 * Default policy drops static methods (ports should not force static); pass
 * includeStatic: true to retain them (safe on the PHP >= 8.4 target, which allows
 * static interface methods).
 */
final class DeriveIt
{
    /**
     * Composition-root accessor (F-10 / RP-10): assembles the derivers behind the
     * {@see DeriveItFactory} so the facade stays a thin, stateless entry point.
     * The optional telemetry sink threads through to every assembled deriver
     * (CONSOLIDATION_PLAN.md §5.1).
     */
    private static function factory(DeriveConfig $config, ?DeriveEventSinkInterface $eventSink = null): DeriveItFactory
    {
        return new DeriveItFactory($config, eventSink: $eventSink);
    }

    /**
     * @param ReflectionClass|class-string $class
     */
    public static function derive(ReflectionClass|string $class, bool $includeStatic = false): string
    {
        $config = new DeriveConfig(includeStatic: $includeStatic);

        return self::factory($config)->interfaceDeriver()->deriveSource($class);
    }

    /**
     * @param ReflectionClass|class-string $class
     */
    public static function deriveInterface(ReflectionClass|string $class, bool $includeStatic = false): InterfaceType
    {
        $config = new DeriveConfig(includeStatic: $includeStatic);

        return self::factory($config)->interfaceDeriver()->derive($class);
    }

    /**
     * Per-trait mode: derive one interface per used trait and compose the main
     * interface so it extends exactly those derived trait interfaces.
     *
     * @param ReflectionClass|class-string $class
     */
    public static function derivePerTrait(
        ReflectionClass|string $class,
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): string {
        return self::factory($config, $eventSink)->perTraitDeriver()->deriveSource($class);
    }

    /**
     * @param ReflectionClass|class-string $class
     * @return array<string, InterfaceType> keyed by trait short name
     */
    public static function deriveTraitInterfaces(
        ReflectionClass|string $class,
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): array {
        return self::factory($config, $eventSink)->perTraitDeriver()->deriveTraitInterfaces(
            is_string($class) ? new ReflectionClass($class) : $class,
        );
    }

    /**
     * Batch family derivation (Phase 4.2): derive a set of classes together so that
     * cross-referencing types are rewritten to their derived interface FQCNs.
     *
     * @param array<int, class-string> $classes
     * @return array<class-string, string> keyed by class FQCN -> rendered interface source
     */
    public static function deriveFamily(
        array $classes,
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): array {
        $derived = self::factory($config, $eventSink)->familyDeriver()->derive($classes);

        $result = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $interface = $derived[$reflection->getName()];
            $result[$reflection->getName()] = self::factory($config)
                ->sourceRenderer()
                ->render($interface, $reflection->getNamespaceName())
            ;
        }

        return $result;
    }

    /**
     * Adapter derivation (Phase 5): derive the self-contained interface for $class
     * and generate the delegating adapter that implements it, wrapping a concrete
     * adaptee. The returned source defines both the interface and the adapter in
     * the adaptee's own namespace so it is self-contained.
     *
     * @param ReflectionClass|class-string $class
     * @param array<string, string> $returnWrapperMap Derived-interface FQCN => adapter class FQCN.
     * @throws ReflectionException
     */
    public static function deriveAdapter(
        ReflectionClass|string $class,
        array $returnWrapperMap = [],
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): string {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;

        $interface = self::factory($config, $eventSink)->interfaceDeriver()->derive($reflection);

        return self::factory($config, $eventSink)
            ->adapterGenerator($interface, $reflection, $returnWrapperMap)
            ->generateSource()
        ;
    }

    /**
     * Derive and return a structured {@see DerivationResult} (D1) carrying the
     * interface, FQCN, applied substitutions, and surface.
     *
     * @param ReflectionClass|class-string $class
     */
    public static function deriveWithResult(
        ReflectionClass|string $class,
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): DerivationResult {
        return self::factory($config, $eventSink)->interfaceDeriver()->deriveWithResult($class);
    }

    /**
     * Derive a whole family and return a single source string grouping every
     * derived interface by namespace (C4). Each member is derived once and
     * cross-references are rewritten to derived interface FQCNs.
     *
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     */
    public static function deriveFamilySource(
        array $classes,
        array $namespacePrefixMap = [],
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): string {
        return self::factory($config, $eventSink)->familyDeriver()->deriveSource($classes, $namespacePrefixMap);
    }

    /**
     * Auto-discover the family reachable from a root class and derive it (C3).
     *
     * @param array<string, string> $namespacePrefixMap
     * @return array<class-string, string> keyed by class FQCN -> rendered interface source
     */
    public static function deriveFamilyFromRoot(
        string $rootClass,
        array $namespacePrefixMap = [],
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): array {
        $derived = self::factory($config, $eventSink)->familyDeriver()->deriveFromRoot($rootClass, $namespacePrefixMap);

        $result = [];
        foreach ($derived as $fqcn => $interface) {
            $reflection = new ReflectionClass($fqcn);
            $result[$fqcn] = self::factory($config)
                ->sourceRenderer()
                ->render($interface, $reflection->getNamespaceName())
            ;
        }

        return $result;
    }

    /**
     * Family-of-adapters generation (G14 / EC12): derive the whole family of
     * interfaces and emit a delegating adapter per member in a single source,
     * reusing {@see AdapterGenerator}. A member whose method returns another
     * member's derived interface is wired to that member's adapter automatically,
     * so the port stays free of concrete adaptee types; the optional
     * $returnWrapperMap overrides or extends that wiring (e.g., for external
     * third-party wrappers).
     *
     * @param array<int, class-string> $classes
     * @param array<string, string> $returnWrapperMap Derived-interface FQCN => adapter class FQCN.
     */
    public static function deriveFamilyAdapter(
        array $classes,
        array $returnWrapperMap = [],
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): string {
        $interfaces = self::factory($config, $eventSink)->familyDeriver()->derive($classes);

        // Deduplicate input classes so each member is derived and emitted once.
        $reflections = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $reflections[$reflection->getName()] = $reflection;
        }

        // Auto-wire each member's derived interface to its own generated adapter
        // (the FQCN used as a wrapper-map key matches the substituted return type
        // rendered onto the interface methods).
        $autoWrapperMap = [];
        foreach ($reflections as $reflection) {
            $interface = $interfaces[$reflection->getName()];
            $namespace = $reflection->getNamespaceName();
            $interfaceFqcn = Naming::join($namespace, $interface->getName());
            $adapterFqcn = Naming::join($namespace, Naming::adapterShortName($reflection));
            $autoWrapperMap[$interfaceFqcn] = $adapterFqcn;
        }

        $wrapperMap = [...$autoWrapperMap, ...$returnWrapperMap];

        $byNamespace = [];
        foreach ($reflections as $reflection) {
            $namespace = $reflection->getNamespaceName();
            $adapter = self::factory($config, $eventSink)
                ->adapterGenerator($interfaces[$reflection->getName()], $reflection, $wrapperMap)
                ->generate()
            ;
            $byNamespace[$namespace][] = [$interfaces[$reflection->getName()], $adapter];
        }

        $file = new PhpFile();
        foreach ($byNamespace as $namespace => $members) {
            $ns = new PhpNamespace($namespace);
            foreach ($members as [$interface, $adapter]) {
                $ns->add($interface);
                $ns->add($adapter);
            }
            $file->addNamespace($ns);
        }

        return (string) $file;
    }

    /**
     * Metadata-only derivation for tooling / IDE use (G9 / D2). Returns a
     * structured {@see DerivationResult} without rendering the source, so callers
     * can introspect the derived surface, FQCN, and applied substitutions cheaply.
     *
     * @param ReflectionClass|class-string $class
     */
    public static function analyze(
        ReflectionClass|string $class,
        DeriveConfig $config = new DeriveConfig(),
    ): DerivationResult {
        return self::factory($config)->interfaceDeriver()->analyze($class);
    }

    /**
     * Batch derivation entry point (G9 / D2). Derives every class and returns a
     * structured {@see DerivationResult} keyed by class FQCN. Each result carries
     * the rendered source so callers can both introspect and persist.
     *
     * @param iterable<class-string|ReflectionClass> $classes
     * @return array<class-string, DerivationResult>
     */
    public static function deriveMany(
        iterable $classes,
        DeriveConfig $config = new DeriveConfig(),
    ): array {
        $deriver = self::factory($config)->interfaceDeriver();
        $results = [];

        foreach ($classes as $class) {
            $reflection = is_string($class) ? new ReflectionClass($class) : $class;
            $results[$reflection->getName()] = $deriver->deriveWithResult($reflection);
        }

        return $results;
    }

    /**
     * Dry-run diff for CI drift detection (G9 / EC15 / D5). Derives $class and
     * compares the freshly generated source against $existingSource, reporting
     * whether they diverge and a unified line diff. Emits a `diff.compute`
     * activity and a `drift.detected` / `drift.clean` occurrence when an event
     * sink is supplied (CONSOLIDATION_PLAN.md §5.2 / C3).
     *
     * @param ReflectionClass|class-string $class
     */
    public static function diff(
        ReflectionClass|string $class,
        string $existingSource,
        DeriveConfig $config = new DeriveConfig(),
        ?DeriveEventSinkInterface $eventSink = null,
    ): DeriveDiff {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
        $target = ['target' => $reflection->getName()];
        $events = self::factory($config, $eventSink)->eventEmitter();

        $events->activityStarted(DeriveEvents::ACTIVITY_DIFF_COMPUTE, $target);
        $generated = self::factory($config)->interfaceDeriver()->deriveSource($reflection);
        $result = DeriveDiff::compute($existingSource, $generated);
        $events->activityFinished(DeriveEvents::ACTIVITY_DIFF_COMPUTE);

        $events->occurred(
            $result->drift
                ? DeriveEvents::OCCURRENCE_DRIFT_DETECTED
                : DeriveEvents::OCCURRENCE_DRIFT_CLEAN,
            $target,
        );

        return $result;
    }
}
