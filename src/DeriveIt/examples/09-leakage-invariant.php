<?php

declare(strict_types=1);

/*
 * 09 — The leakage invariant.
 *
 * A derived interface must never reference the adaptee's own contracts. Even when
 * the source class implements other interfaces, the derived interface is flat and
 * self-contained: empty `extends`, no `use`, nothing leaking the adaptee hierarchy.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(_Fixture\ImplementsMultipleClass::class);

show(
    'ImplementsMultipleClass — flat & self-contained',
    $source,
    'The class implements PreExistingInterface AND SecondPreExistingInterface, '
    . 'yet the derived interface extends neither. That is the leakage invariant.',
);

$leaks = (str_contains($source, 'extends PreExistingInterface')
    || str_contains($source, 'extends SecondPreExistingInterface'))
    ? 'LEAK DETECTED' : 'no leakage';

echo "\033[1;33mLeakage check:\033[0m {$leaks}\n";
