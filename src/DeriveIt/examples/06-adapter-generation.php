<?php

declare(strict_types=1);

/*
 * 06 — Adapter generation.
 *
 * Beyond the interface, DeriveIt can emit a delegating adapter that implements the
 * derived interface and wraps a concrete adaptee instance. The generated file
 * defines both the interface and the adapter, so it is self-contained.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(_Fixture\Service::class);

show(
    'Adapter for ' . _Fixture\Service::class,
    $source,
    'ServiceAdapter holds a Service via constructor injection and forwards every interface method to it.',
);
