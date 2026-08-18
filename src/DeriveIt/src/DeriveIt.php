<?php

declare(strict_types=1);

namespace ItPack\DeriveIt;

use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;

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
     * @param ReflectionClass|class-string $class
     */
    public static function derive(ReflectionClass|string $class, bool $includeStatic = false): string
    {
        return new InterfaceDeriver(config: new DeriveConfig(includeStatic: $includeStatic))->deriveSource($class);
    }

    /**
     * @param ReflectionClass|class-string $class
     */
    public static function deriveInterface(ReflectionClass|string $class, bool $includeStatic = false): InterfaceType
    {
        return new InterfaceDeriver(config: new DeriveConfig(includeStatic: $includeStatic))->derive($class);
    }

    /**
     * Per-trait mode: derive one interface per used trait and compose the main
     * interface so it extends exactly those derived trait interfaces.
     *
     * @param ReflectionClass|class-string $class
     */
    public static function derivePerTrait(ReflectionClass|string $class): string
    {
        return new PerTraitInterfaceDeriver()->deriveSource($class);
    }

    /**
     * @param ReflectionClass|class-string $class
     * @return array<string, InterfaceType> keyed by trait short name
     */
    public static function deriveTraitInterfaces(ReflectionClass|string $class): array
    {
        return new PerTraitInterfaceDeriver()->deriveTraitInterfaces(
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
    public static function deriveFamily(array $classes): array
    {
        $derived = new FamilyDeriver()->derive($classes);

        $result = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $interface = $derived[$reflection->getName()];
            $result[$reflection->getName()] = self::renderInterface($interface, $reflection->getNamespaceName());
        }

        return $result;
    }

    private static function renderInterface(InterfaceType $interface, string $namespace): string
    {
        $ns = new PhpNamespace($namespace);
        $ns->add($interface);

        $file = new PhpFile();
        $file->addNamespace($ns);

        return (string) $file;
    }
}
