<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\DeriverInterface;
use ItPack\DeriveIt\Exception\EmptyDerivationException;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use Nette\PhpGenerator\InterfaceType;
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
 */
final class InterfaceDeriver implements DeriverInterface
{
    private readonly MethodExtractor $extractor;
    private readonly SourceRenderer $renderer;
    private readonly MethodSignatureFactory $signatureFactory;

    public function __construct(
        ?MethodSignatureFactory $signatureFactory = null,
        private readonly DeriveConfig $config = new DeriveConfig(),
    ) {
        $this->extractor = new MethodExtractor($config);
        $this->renderer = new SourceRenderer();
        $this->signatureFactory = $signatureFactory ?? new MethodSignatureFactory(config: $config);
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

        return $this->renderer->render($meta->interface, $reflection->getNamespaceName());
    }

    /**
     * Derive and return a structured {@see DerivationResult} (D1).
     */
    public function deriveWithResult(ReflectionClass|string $class): DerivationResult
    {
        $reflection = $this->reflect($class);
        $meta = $this->build($reflection);

        return new DerivationResult(
            interface: $meta->interface,
            fqcn: Naming::derivedInterfaceFqcn($reflection),
            shortName: Naming::derivedInterfaceShortName($reflection),
            source: $this->renderer->render($meta->interface, $reflection->getNamespaceName()),
            methods: $meta->methods,
            substitutions: $meta->substitutions,
            constants: $meta->constants,
        );
    }

    /**
     * @return object{interface: InterfaceType, methods: list<string>, substitutions: array<string,string>, constants: list<string>}
     */
    private function build(ReflectionClass $reflection, ?TypeSubstitutor $substitutor = null, array $allowedNamespaces = []): object
    {
        Naming::assertNoCollision($reflection);

        $selfFqcn = $this->config->substituteSelfStatic ? Naming::derivedInterfaceFqcn($reflection) : null;
        $parent = $reflection->getParentClass();
        $parentFqcn = ($this->config->substituteSelfStatic && $parent !== false)
            ? Naming::derivedInterfaceFqcn($parent)
            : null;

        if ($substitutor === null) {
            // Seed the map with the class itself and its parent so that `self`
            // (which reflection resolves to the concrete class name) and `parent`
            // are rewritten to their derived interface FQCNs (A1).
            $typeMap = $this->config->typeSubstitutionMap;
            if ($this->config->substituteSelfStatic) {
                $typeMap[$reflection->getName()] = Naming::derivedInterfaceFqcn($reflection);
                $parentReflection = $reflection->getParentClass();
                if ($parentReflection !== false) {
                    $typeMap[$parentReflection->getName()] = Naming::derivedInterfaceFqcn($parentReflection);
                }
            }

            $substitutor = new TypeSubstitutor($typeMap, $selfFqcn, $parentFqcn);
            $allowedNamespaces = LeakageAuditor::allowedNamespaces($reflection->getNamespaceName(), $typeMap, []);
        }

        $interface = new InterfaceType(Naming::derivedInterfaceShortName($reflection));

        $collected = [];
        foreach ($this->extractor->extract($reflection) as $method) {
            $name = $method->getName();
            if ($this->shouldSkipMethod($name)) {
                continue;
            }

            $collected[] = $this->signatureFactory->fromMethod($method, $substitutor);
        }

        $methodNames = array_map(static fn (\Nette\PhpGenerator\Method $m): string => $m->getName(), $collected);

        $interface->setMethods($collected);

        $constants = [];
        if ($this->config->includeConstants) {
            $constants = $this->addConstants($interface, $reflection);
        }

        if ($this->config->failOnEmpty && $methodNames === [] && $constants === []) {
            throw new EmptyDerivationException(sprintf(
                'Derivation of "%s" produced an empty surface (no methods, no constants).',
                $reflection->getName(),
            ));
        }

        self::assertSelfContained($interface);

        new LeakageAuditor()->audit(
            $interface,
            $reflection->getNamespaceName(),
            $allowedNamespaces,
            $this->config->auditLeakage,
        );

        $substitutions = $this->config->typeSubstitutionMap;
        if ($selfFqcn !== null) {
            $substitutions['self'] = $selfFqcn;
            $substitutions['static'] = $selfFqcn;
        }
        if ($parentFqcn !== null) {
            $substitutions['parent'] = $parentFqcn;
        }

        return (object) [
            'interface' => $interface,
            'methods' => $methodNames,
            'substitutions' => $substitutions,
            'constants' => $constants,
        ];
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

    private function reflect(ReflectionClass|string $class): ReflectionClass
    {
        return is_string($class) ? new ReflectionClass($class) : $class;
    }

    /**
     * Asserts the core leakage invariant (flat mode): no extends, no trait uses.
     */
    public static function assertSelfContained(InterfaceType $interface): void
    {
        if ($interface->getExtends() !== []) {
            throw new LeakageDetectedException(sprintf(
                'Derived interface "%s" must not extend anything (adaptee leakage).',
                $interface->getName(),
            ));
        }
    }
}
