<?php

declare(strict_types=1);

/*
 * 06 — Adapter generation (the basics).
 *
 * Beyond the interface, DeriveIt can emit a delegating adapter that implements the
 * derived interface and wraps a concrete adaptee instance. The generated file
 * defines both the interface and the adapter, so it is self-contained.
 *
 * Note: by default a returned type stays concrete (see ServiceInterface::collaborator()
 * below returns Collaborator, not a port). To wrap returned ports, see example 11
 * (explicit return-wrapper map) and example 12 (a whole family of adapters, auto-wired).
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(_Fixture\Service::class);

show(
    'Adapter for ' . _Fixture\Service::class,
    $source,
    'ServiceAdapter holds a Service via constructor injection and forwards every interface method to it. '
    . 'Returned types stay concrete here — examples/11 and examples/12 show how to wrap them into ports.',
);
