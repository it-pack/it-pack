<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Exception\NamingCollisionException;
use ReflectionClass;

/**
 * Centralizes derived-interface naming so every deriver agrees on the short name,
 * FQCN, and collision rules (maintainability F3). The rules live here instead of
 * being duplicated across InterfaceDeriver / PerTraitInterfaceDeriver.
 */
final class Naming
{
    /**
     * Short name of the interface derived from a class (e.g. Foo => FooInterface).
     */
    public static function derivedInterfaceShortName(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();

        return str_ends_with($shortName, 'Interface')
            ? $shortName . 'Derived'
            : $shortName . 'Interface';
    }

    /**
     * Fully-qualified name of the interface derived from a class.
     */
    public static function derivedInterfaceFqcn(ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();

        return ($namespace === '' ? '' : $namespace . '\\') . self::derivedInterfaceShortName($reflection);
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
     * @param list<\Nette\PhpGenerator\Method> $methods
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
