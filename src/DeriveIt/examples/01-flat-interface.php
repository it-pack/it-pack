<?php

declare(strict_types=1);

/*
 * 01 — The core: flat interface derivation.
 *
 * Point DeriveIt at any class and it returns a self-contained interface source
 * describing that class's public surface. This is the simplest, most common use.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(_Fixture\Service::class);

show(
    'DeriveIt::derive(' . _Fixture\Service::class . ')',
    $source,
    'A clean port you own — no constructors, no static, no adaptee leakage.',
);
