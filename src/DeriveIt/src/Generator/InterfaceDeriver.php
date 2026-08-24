<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Reflection\ReflectionCacheInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;
use ItPack\DeriveIt\Exception\EmptyDerivationException;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use ReflectionClass;

/**
 * Assembles a self-contained interface from a class's effective public surface.
 *
 * Leakage invariant: a flat-derived interface must extend / reference nothing from
 * the adaptee's own parent classes, traits, or interfaces. In flat mode the
 * assembled InterfaceType therefore has an empty extends list and no trait uses.
 *
 * Phase 9 additions: self / static / parent return types are rewritten to the
 * derived interface FQCN (A1); constant defaults are rewritten when mapped (A2);
 * public constants, allow/deny method lists, attribute pass-through and `@throws`
 * capture are opt-in (B1 / B3 / B2); naming collisions are detected (A5); an empty
 * surface can be escalated to an error (A6); and a structured {@see DerivationResult}
 * is available alongside the source string (D1).
 *
 * Phase 10.15 (G15 / G18): {@see BuildMeta} replaces the untyped `build()` return;
 * substitutor seeding + skip filtering + reflection memoization live in the shared
 * {@see AbstractDeriver} base.
 */
final readonly class InterfaceDeriver extends AbstractDeriver
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

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        return $this->build($this->reflect($class))->interface;
    }

    /**
     * Derive using a caller-supplied substitutor (used by FamilyDeriver to inject
     * the family map plus owned-namespace prefix rewriting).
     */
    public function deriveWithSubstitutor(
        ReflectionClass $reflection,
        TypeSubstitutor $substitutor,
        array $allowedNamespaces = [],
    ): InterfaceType {
        return $this->build($reflection, $substitutor, $allowedNamespaces)->interface;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = $this->reflect($class);
        $meta = $this->build($reflection);

        $source = $this->renderer->render($meta->interface, $reflection->getNamespaceName());
        $this->events->measured('source.bytes', strlen($source), 'By', ['target' => $reflection->getName()]);

        return $source;
    }

    /**
     * Derive and return a structured {@see DerivationResult} (D1).
     */
    public function deriveWithResult(ReflectionClass|string $class): DerivationResult
    {
        $reflection = $this->reflect($class);
        $meta = $this->build($reflection);

        $source = $this->renderer->render($meta->interface, $reflection->getNamespaceName());
        $this->events->measured('source.bytes', strlen($source), 'By', ['target' => $reflection->getName()]);

        return new DerivationResult(
            interface: $meta->interface,
            fqcn: Naming::derivedInterfaceFqcn($reflection),
            shortName: Naming::derivedInterfaceShortName($reflection),
            source: $source,
            methods: $meta->methods,
            substitutions: $meta->substitutions,
            constants: $meta->constants,
        );
    }

    /**
     * Metadata-only derivation for tooling / IDE use (G9 / D2): build the same
     * structured {@see DerivationResult} but skip source rendering, so callers can
     * introspect the surface, FQCN, and substitutions without paying to render.
     */
    public function analyze(ReflectionClass|string $class): DerivationResult
    {
        $reflection = $this->reflect($class);
        $meta = $this->build($reflection);

        return new DerivationResult(
            interface: $meta->interface,
            fqcn: Naming::derivedInterfaceFqcn($reflection),
            shortName: Naming::derivedInterfaceShortName($reflection),
            source: null,
            methods: $meta->methods,
            substitutions: $meta->substitutions,
            constants: $meta->constants,
        );
    }

    /**
     * @return BuildMeta
     */
    private function build(ReflectionClass $reflection, ?TypeSubstitutor $substitutor = null, array $allowedNamespaces = []): BuildMeta
    {
        Naming::assertNoCollision($reflection);

        $target = ['target' => $reflection->getName()];
        $this->events->occurred('derivation.started', $target);

        if ($substitutor === null) {
            // Seed the map with the class itself and its parent so that `self`
            // (which reflection resolves to the concrete class name) and `parent`
            // are rewritten to their derived interface FQCNs (A1). Shared from the
            // AbstractDeriver base so flat + per-trait modes stay in lockstep.
            $substitutor = $this->createTypeSubstitutor($reflection);
            $allowedNamespaces = LeakageAuditor::allowedNamespaces($reflection->getNamespaceName(), $this->seedTypeMap($reflection), []);
        }

        $interface = new InterfaceType(Naming::derivedInterfaceShortName($reflection));

        // G16 / A4: re-expose owned interfaces the adaptee implements by carrying
        // them onto the derived interface via `extends`. Only interfaces explicitly
        // listed in the allow-list and actually implemented are re-exposed; the core
        // leakage invariant otherwise keeps `extends` empty. A listed-but-unimplemented
        // entry is a configuration mistake worth surfacing rather than skipping
        // silently (first `observed()` emission site — CONSOLIDATION_PLAN §5.2).
        $implemented = $reflection->getInterfaceNames();
        foreach ($this->config->reExposeOwnedInterfaces as $owned) {
            if (!in_array($owned, $implemented, true)) {
                $this->events->observed(
                    sprintf('reExposeOwnedInterfaces entry "%s" is not implemented by "%s"; skipped.', $owned, $reflection->getName()),
                    DeriveObservationSeverity::WARNING,
                    ['target' => $reflection->getName(), 'interface' => $owned],
                );

                continue;
            }

            $interface->addExtend($owned);
        }

        $this->events->activityStarted('surface.extract', $target);
        $extracted = $this->extractor->extract($reflection);
        $collected = [];
        foreach ($extracted as $method) {
            $signature = $this->signatureFor($method, $substitutor);
            if ($signature === null) {
                continue;
            }
            $collected[] = $signature;
        }
        $this->events->activityFinished('surface.extract');
        $this->events->measured('methods.derived', count($collected), 'count', $target);
        $this->events->measured('methods.dropped', max(0, count($extracted) - count($collected)), 'count', $target);

        $methodNames = array_map(static fn (Method $m): string => $m->getName(), $collected);

        Naming::assertMethodNamesUnique($interface->getName(), $collected);
        $interface->setMethods($collected);

        $constants = [];
        if ($this->config->includeConstants) {
            $constants = $this->addConstants($interface, $reflection);
            $this->events->measured('constants.lifted', count($constants), 'count', $target);
        }

        if ($this->config->failOnEmpty && $methodNames === [] && $constants === []) {
            throw new EmptyDerivationException(sprintf(
                'Derivation of "%s" produced an empty surface (no methods, no constants).',
                $reflection->getName(),
            ));
        }

        $this->events->activityStarted('leakage.audit', $target);
        self::assertSelfContained($interface, $this->config->reExposeOwnedInterfaces);

        new LeakageAuditor()->audit(
            $interface,
            $reflection->getNamespaceName(),
            $allowedNamespaces,
            $this->config->auditLeakage,
        );
        $this->events->activityFinished('leakage.audit');

        // Report the effective map this substitutor actually applied (a full family
        // cross-reference map in family mode), plus the self/static/parent keyword
        // rewrites that reflection surfaces verbatim (A1). The substitutor is the
        // single owner of those values — report from it, never recompute (R3-7).
        $substitutions = $substitutor->getMap();

        $selfFqcn = $substitutor->selfFqcn();
        if ($selfFqcn !== null) {
            $substitutions['self'] = $selfFqcn;
            $substitutions['static'] = $selfFqcn;
        }

        $parentFqcn = $substitutor->parentFqcn();
        if ($parentFqcn !== null) {
            $substitutions['parent'] = $parentFqcn;
        }

        $this->events->measured('substitutions.applied', count($substitutions), 'count', $target);
        $this->events->occurred('derivation.completed', $target);

        return new BuildMeta($interface, $methodNames, $substitutions, $constants);
    }

    /**
     * Lifts the class's own public constants into the interface (B1). Only scalar,
     * null, and array-of-scalar values are carried to avoid leaking expressions
     * that reference the adaptee.
     *
     * @return list<string>
     */
    private function addConstants(InterfaceType $interface, ReflectionClass $reflection): array
    {
        $names = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!$constant->isPublic() || $constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $value = $constant->getValue();
            if (!is_scalar($value) && $value !== null && !$this->isScalarArray($value)) {
                continue;
            }

            $interface->addConstant($constant->getName(), $value);
            $names[] = $constant->getName();
        }

        return $names;
    }

    private function isScalarArray(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Asserts the core leakage invariant (flat mode): no extends, no trait uses —
     * except owned interfaces explicitly re-exposed via the G16 allow-list.
     *
     * @param list<string> $allowedExtends Owned interface FQCNs permitted in `extends`.
     */
    public static function assertSelfContained(InterfaceType $interface, array $allowedExtends = []): void
    {
        $unexpected = array_diff($interface->getExtends(), $allowedExtends);
        if ($unexpected !== []) {
            throw new LeakageDetectedException(sprintf(
                'Derived interface "%s" must not extend non-owned interface(s): %s (adaptee leakage).',
                $interface->getName(),
                implode(', ', $unexpected),
            ));
        }
    }
}
