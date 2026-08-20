<?php

declare(strict_types=1);

/*
 * 03 — Type fidelity.
 *
 * DeriveIt reuses nette/php-generator's reflection-to-source round-trip, so every
 * kind of PHP type is reproduced faithfully in the derived interface: unions,
 * intersections, variadics, by-ref params, nullables, enums, and self/static.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$classes = [
    _Fixture\UnionTypeClass::class,
    _Fixture\IntersectionTypeClass::class,
    _Fixture\VariadicAndByRefClass::class,
    _Fixture\NullableTypeClass::class,
    _Fixture\ConsumesEnumParamClass::class,
    _Fixture\SelfStaticParentReturnClass::class,
];

foreach ($classes as $class) {
    show(
        'Type fidelity: ' . $class,
        DeriveIt::derive($class),
        'nette/php-generator preserves the full type grammar (union, intersection, variadic, by-ref, enum, self/static).',
    );
}
