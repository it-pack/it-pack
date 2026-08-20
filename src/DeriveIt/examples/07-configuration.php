<?php

declare(strict_types=1);

/*
 * 07 — Configuration: every filtering decision is an explicit flag.
 *
 * DeriveConfig is the single knob for visibility, static/magic inclusion, and
 * constructor dropping. Defaults reproduce the safe "port" behaviour; nothing is
 * ever dropped by accident.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;

// Public-only (default visibility).
show(
    'VisibilityMixClass — default (public only)',
    DeriveIt::derive(_Fixture\VisibilityMixClass::class),
    'Only publicMethod() is included.',
);

// Public + protected, plus static and magic, via an explicit DeriveConfig.
$config = new DeriveConfig(
    visibility: DeriveConfig::VISIBILITY_PROTECTED,
    includeStatic: true,
    includeMagic: true,
);

$source = (new InterfaceDeriver(config: $config))
    ->deriveSource(_Fixture\VisibilityMixClass::class);

show(
    'VisibilityMixClass — visibility=protected, static+magic on',
    $source,
    'Now publicMethod() AND protectedMethod() appear; privateMethod() is still excluded.',
);
