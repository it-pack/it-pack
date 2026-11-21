<?php

declare(strict_types=1);

/*
 * 16 — Destination namespace: DeriveConfig::$namespace places every rendered
 * derived interface under a namespace you own instead of the adaptee's reflected
 * namespace. null (the default) preserves the existing reflected-namespace
 * behaviour. Honoured by flat, per-trait, and family derivation.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;

// --- Flat ---------------------------------------------------------------

// Default: the derived interface lands in the adaptee's reflected namespace.
$default = DeriveIt::derive(_Fixture\VisibilityMixClass::class);

show(
    'VisibilityMixClass — default (reflected namespace _Fixture)',
    $default,
    'The interface is declared in namespace _Fixture.',
);

// With a configured namespace: everything renders under Custom\Port instead.
// Flat mode goes through InterfaceDeriver directly when a full config is needed.
$config = new DeriveConfig()->withNamespace('Custom\\Port');
$port = new InterfaceDeriver(config: $config)
    ->deriveSource(_Fixture\VisibilityMixClass::class)
;

show(
    'VisibilityMixClass — namespace Custom\Port',
    $port,
    'The interface is now declared in namespace Custom\Port.',
);

// --- Per-trait ----------------------------------------------------------

// One interface per trait plus the composed main interface, all under the
// same configured namespace.
$perTrait = DeriveIt::derivePerTrait(
    _Fixture\Stubs\ClassD::class,
    config: new DeriveConfig()->withNamespace('Custom\\Port'),
);

show(
    'ClassD per-trait — namespace Custom\Port',
    $perTrait,
    'TraitInterface and the composed main interface share Custom\Port.',
);

// --- Family -------------------------------------------------------------

// Cross-referencing members (Node <-> Tree) all land under Custom\Port with
// their cross references rewritten to the derived interfaces.
$family = DeriveIt::deriveFamily(
    [_Fixture\Node::class, _Fixture\Tree::class],
    config: new DeriveConfig()->withNamespace('Custom\\Port'),
);

show(
    'Node/Tree family — namespace Custom\Port',
    implode("\n\n", $family),
    'Both interfaces render into namespace Custom\Port.',
);

// Auto-discovered from a root, likewise grouped under the configured namespace.
$fromRoot = DeriveIt::deriveFamilyFromRoot(
    _Fixture\Node::class,
    namespacePrefixMap: [],
    config: new DeriveConfig()->withNamespace('Custom\\Port'),
);

show(
    'Family from root — namespace Custom\Port',
    implode("\n\n", $fromRoot),
    'Auto-discovered members share Custom\Port as well.',
);
