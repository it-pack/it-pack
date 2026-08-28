<?php

declare(strict_types=1);

/*
 * 02 — What gets dropped, and how to keep it.
 *
 * By default a derived interface drops constructors (an interface can't declare
 * one), static methods, and magic __* methods — the things that don't belong on a
 * port you own. Each can be opted into.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;

// A constructor-only class derives to an EMPTY interface.
show(
    'ReadonlyClass (constructor only)',
    DeriveIt::derive(_Fixture\ReadonlyClass::class),
    'Constructors can\'t live in an interface, so this class yields an empty one.',
);

// Static methods are dropped unless you ask for them.
show(
    'StaticHeavyClass — default (static dropped)',
    DeriveIt::derive(_Fixture\StaticHeavyClass::class),
    'Only instanceMethod() survives; staticPublicMethod() is gone by default.',
);

show(
    'StaticHeavyClass — includeStatic: true',
    DeriveIt::derive(_Fixture\StaticHeavyClass::class, includeStatic: true),
    'Opt in to retain static methods (safe on PHP >= 8.4, which permits static interface methods).',
);

// Magic methods are dropped unless you ask for them (via the config).
$magic = new InterfaceDeriver(config: new DeriveConfig(includeMagic: true))
    ->deriveSource(_Fixture\MagicMethodsClass::class);

show(
    'MagicMethodsClass — includeMagic: true',
    $magic,
    'Magic __* methods are dropped by default; includeMagic keeps them.',
);
