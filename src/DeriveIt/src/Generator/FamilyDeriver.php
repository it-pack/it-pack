<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;

/**
 * Derives a whole family of classes together so that types referencing one another
 * are rewritten to the derived interface FQCNs (Phase 4.2 batch auto-detection).
 *
 * The substitution map is built by declaring: for every class in the family,
 * `<ClassFQCN> => <DerivedInterfaceFQCN>`. Each member is then derived with that
 * map, so a method returning a sibling type points at its derived interface.
 */
final class FamilyDeriver
{
    /**
     * @param array<int, class-string> $classes
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function derive(array $classes): array
    {
        $map = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $map[$reflection->getName()] = InterfaceDeriver::derivedInterfaceFqcn($reflection);
        }

        $deriver = new InterfaceDeriver(config: new DeriveConfig(typeSubstitutionMap: $map));

        $result = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $result[$reflection->getName()] = $deriver->derive($reflection);
        }

        return $result;
    }
}
