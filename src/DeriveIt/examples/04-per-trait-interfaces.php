<?php

declare(strict_types=1);

/*
 * 04 — Interface Segregation: one interface per trait.
 *
 * Instead of one flat interface, derive one interface per used trait and compose
 * the main interface so it `extends` exactly those derived trait interfaces. This
 * is Interface Segregation applied to the derivation tool itself.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

// The composed main interface extends one interface per used trait.
show(
    'Per-trait: main interface for ' . _Fixture\Stubs\ClassD::class,
    DeriveIt::derivePerTrait(_Fixture\Stubs\ClassD::class),
    'ClassDInterface extends TraitEInterface, TraitAInterface — and declares nothing of its own.',
);

// Each trait contributes its own self-contained interface.
$traitInterfaces = DeriveIt::deriveTraitInterfaces(_Fixture\Stubs\ClassD::class);

foreach ($traitInterfaces as $name => $interface) {
    show(
        '  trait interface: ' . $name,
        (string) $interface,
        'Only the methods declared on that trait are present.',
    );
}
