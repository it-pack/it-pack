<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Exception\NamingCollisionException;
use Nette\PhpGenerator\Method;
use ReflectionClass;

/**
 * Centralizes derived-interface naming so every deriver agrees on the short name,
 * FQCN, and collision rules (maintainability F3). The rules live here instead of
 * being duplicated across InterfaceDeriver / PerTraitInterfaceDeriver.
 */
final class Naming
{
    /**
     * Suffix appended to a derived interface's short name (naming contract).
     */
    public const INTERFACE_SUFFIX = 'Interface';

    /**
     * Suffix appended to a generated adapter's short name (naming contract).
     */
    public const ADAPTER_SUFFIX = 'Adapter';

    /**
     * Marker appended when an input name already ends in {@see self::INTERFACE_SUFFIX},
     * so deriving from an interface-shaped name stays collision-free.
     */
    private const DERIVED_MARKER = 'Derived';

    /**
     * Short name of an arbitrary FQCN: the substring after the last `\` (or the
     * whole string when it is already unqualified). DRY helper for the inline
     * `substr($fqcn, (int) strrpos($fqcn, '\\') + 1)` copies scattered across the
     * CLI and derivers (F-5 / RP-5).
     */
    public static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /**
     * Unconditional interface-suffix append. Deliberately NOT the same rule as
     * {@see self::derivedInterfaceShortName()}: per-trait mode must keep producing
     * `<Trait>Interface` even when the trait itself ends in `Interface`
     * (e.g. LogInterface => LogInterfaceInterface), so no `Derived` marker logic
     * applies here.
     */
    public static function interfaceShortName(string $shortName): string
    {
        return $shortName . self::INTERFACE_SUFFIX;
    }

    /**
     * Short name of the delegating adapter generated for a concrete adaptee.
     */
    public static function adapterShortName(ReflectionClass $adaptee): string
    {
        return $adaptee->getShortName() . self::ADAPTER_SUFFIX;
    }

    /**
     * Joins a namespace and a short name into `$ns\Short`, or returns the short
     * name verbatim when the namespace is empty (the global namespace).
     */
    public static function join(string $namespace, string $shortName): string
    {
        return ($namespace === '' ? '' : $namespace . '\\') . $shortName;
    }

    /**
     * Short name of the interface derived from a class (e.g. Foo => FooInterface).
     */
    public static function derivedInterfaceShortName(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();

        return str_ends_with($shortName, self::INTERFACE_SUFFIX)
            ? $shortName . self::DERIVED_MARKER
            : self::interfaceShortName($shortName);
    }

    /**
     * Fully qualified name of the interface derived from a class.
     */
    public static function derivedInterfaceFqcn(ReflectionClass $reflection): string
    {
        return self::join($reflection->getNamespaceName(), self::derivedInterfaceShortName($reflection));
    }

    /**
     * Throws when the derived interface FQCN already names an existing concrete
     * type (class or trait) in the target namespace (NamingCollisionException, A5).
     *
     * Interface collisions are intentionally not fatal: the generator's own output
     * is an interface, and re-deriving an already-loaded generated interface within
     * the same process is a benign, identical redefinition rather than a clash with
     * adaptee code. Concrete class/trait collisions, however, would shadow real
     * adaptee types and are therefore escalated.
     */
    public static function assertNoCollision(ReflectionClass $reflection): void
    {
        $fqcn = self::derivedInterfaceFqcn($reflection);

        if ($fqcn === $reflection->getName()) {
            return;
        }

        if (class_exists($fqcn, false) || trait_exists($fqcn, false) || enum_exists($fqcn, false)) {
            throw NamingCollisionException::forFqcn($fqcn);
        }
    }

    /**
     * Throws when a derived interface would declare the same method name twice
     * (e.g. after a `methodRenames` map collides). Must be checked on the
     * collected method list BEFORE `setMethods()`, because Nette collapses
     * same-named methods and would otherwise silently drop the duplicate
     * (G11 / B4).
     *
     * @param list<Method> $methods
     */
    public static function assertMethodNamesUnique(string $interfaceName, array $methods): void
    {
        $seen = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (isset($seen[$name])) {
                throw NamingCollisionException::forMethodName($interfaceName, $name);
            }
            $seen[$name] = true;
        }
    }
}
