<?php

declare(strict_types=1);

/*
 * 08 — The building block underneath: graph walking.
 *
 * DeriveIt's generators sit on top of a recursive graph walker
 * (ClassReflection + the three resolvers). It walks the full parent/interface/
 * trait graph of any class so the generators never have to.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\Reflection\ClassReflection;

$reflection = ClassReflection::create(_Fixture\Stubs\ClassD::class);

$names = static fn(array $reflections): string => implode(
    "\n",
    array_map(static fn(\ReflectionClass $r): string => '  - ' . $r->getName(), $reflections),
);

show(
    'Classes recursively (parents)',
    $names($reflection->getClassesRecursively()),
    'ClassD → ClassC → ClassB → ClassA.',
);

show(
    'Interfaces recursively',
    $names($reflection->getInterfacesRecursively()),
    'Every interface on ClassD and all of its ancestors.',
);

show(
    'Traits recursively (incl. nested)',
    $names($reflection->getTraitsRecursively()),
    'TraitE (used by ClassD) plus TraitA/TraitB/TraitC/TraitD (used up the chain).',
);
