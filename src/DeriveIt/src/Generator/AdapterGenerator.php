<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;

/**
 * Generates a delegating adapter that implements a previously derived interface
 * and wraps a concrete adaptee instance (Phase 5).
 *
 * The adapter holds the adaptee via constructor injection and forwards every
 * interface method to it. When a method's (substituted) return type names a
 * derived interface that has a registered adapter class, the returned value is
 * wrapped in that adapter so the port stays free of concrete types (the
 * leakage invariant extended across the adapter boundary).
 *
 * The generated adapter lives in the adaptee's own namespace so the FQCN types
 * (adaptee + wrapper adapters) simplify to local short names when rendered.
 */
final class AdapterGenerator
{
    /**
     * @param array<string, string> $returnWrapperMap Derived-interface FQCN => adapter class FQCN.
     */
    public function __construct(
        private readonly InterfaceType $interface,
        private readonly ReflectionClass $adaptee,
        private readonly array $returnWrapperMap = [],
    ) {
    }

    public function generate(): ClassType
    {
        $adapteeFqcn = $this->adaptee->getName();
        $adapterName = $this->adaptee->getShortName() . 'Adapter';

        $class = new ClassType($adapterName);
        $class->setFinal(true);
        $class->addImplement($this->interfaceFqcn());

        $class->addProperty('adaptee')
            ->setType($adapteeFqcn)
            ->setReadOnly(true)
            ->setPrivate()
        ;

        $constructor = $class->addMethod('__construct')
            ->setBody('$this->adaptee = $adaptee;')
        ;
        $constructor->addParameter('adaptee')->setType($adapteeFqcn);

        foreach ($this->interface->getMethods() as $method) {
            $this->addDelegatingMethod($class, $method);
        }

        return $class;
    }

    /**
     * FQCN of the derived interface, matching the adaptee's namespace so Nette
     * renders the `implements` clause as a local short name.
     */
    private function interfaceFqcn(): string
    {
        $namespace = $this->adaptee->getNamespaceName();

        return ($namespace === '' ? '' : $namespace . '\\') . $this->interface->getName();
    }

    /**
     * Emits the derived interface and the generated adapter together so the
     * rendered file is self-contained (defines both types in one namespace).
     */
    public function generateSource(): string
    {
        $namespace = new PhpNamespace($this->adaptee->getNamespaceName());
        $namespace->add($this->interface);
        $namespace->add($this->generate());

        $file = new PhpFile();
        $file->addNamespace($namespace);

        return (string) $file;
    }

    private function addDelegatingMethod(ClassType $class, Method $method): void
    {
        $name = $method->getName();
        $returnType = $method->getReturnType();

        $delegating = $class->addMethod($name);
        if (is_string($returnType) && $returnType !== '') {
            $delegating->setReturnType($returnType);
            $delegating->setReturnNullable($method->isReturnNullable());
        }

        $arguments = [];
        $parameters = $method->getParameters();
        $isVariadic = $method->isVariadic();

        foreach ($parameters as $index => $parameter) {
            $isVariadicParameter = $isVariadic && $index === array_key_last($parameters);
            $arguments[] = ($isVariadicParameter ? '...' : '') . '$' . $parameter->getName();

            $copied = $delegating->addParameter($parameter->getName())
                ->setType($parameter->getType())
                ->setNullable($parameter->isNullable())
            ;

            if ($parameter->hasDefaultValue()) {
                $copied->setDefaultValue($parameter->getDefaultValue());
            }

            if ($parameter->isReference()) {
                $copied->setReference(true);
            }
        }

        if ($isVariadic) {
            $delegating->setVariadic(true);
        }

        $argumentList = implode(', ', $arguments);
        $call = sprintf('$this->adaptee->%s(%s)', $name, $argumentList);

        $delegating->setBody($this->bodyFor($call, $returnType, $method->isReturnNullable()));
    }

    /**
     * @param string|null $returnType
     */
    private function bodyFor(string $call, ?string $returnType, bool $nullable): string
    {
        if ($returnType === null || $returnType === 'void') {
            return $call . ';';
        }

        $normalized = str_replace('?', '', $returnType);
        $isUnion = str_contains($normalized, '|');
        $isIntersection = str_contains($normalized, '&');

        $wrappers = [];
        $segments = preg_split('/[|&]/', $normalized);
        foreach ($segments === false ? [] : $segments as $segment) {
            $wrapper = $this->returnWrapperMap[$segment] ?? null;
            if ($wrapper !== null) {
                $wrappers[$segment] = '\\' . ltrim($wrapper, '\\');
            }
        }

        // Single (non-compound) return type: keep the established single-wrapper
        // shape so existing generated output is unchanged.
        if (!$isUnion && !$isIntersection) {
            $segment = $normalized;
            if (!isset($wrappers[$segment])) {
                return sprintf('return %s;', $call);
            }

            if ($nullable) {
                return sprintf(
                    "\$value = %s;\nreturn \$value === null ? null : new %s(\$value);",
                    $call,
                    $wrappers[$segment],
                );
            }

            return sprintf('return new %s(%s);', $wrappers[$segment], $call);
        }

        // Intersection returns are not wrappable; degenerate to a raw delegation.
        if ($isIntersection || $wrappers === []) {
            return sprintf('return %s;', $call);
        }

        // Union return: wrap each segment that has a registered adapter, dispatch
        // on the runtime type. Unmatched segments fall through to the raw value,
        // which still satisfies the derived interface (the concrete implements it).
        $branches = '';
        foreach ($wrappers as $segment => $wrapper) {
            $branches .= sprintf("\t\t\$value instanceof \\%s => new %s(\$value),\n", $segment, $wrapper);
        }

        return sprintf(
            "\$value = %s;\n\t\treturn match (true) {\n%s\t\tdefault => \$value,\n\t};",
            $call,
            $branches,
        );
    }
}
