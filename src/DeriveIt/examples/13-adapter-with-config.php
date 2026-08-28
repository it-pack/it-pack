<?php

declare(strict_types=1);

/*
 * 13 — Adapter with full configuration (the forAdapter() preset).
 *
 * An adapter delegates everything the adaptee exposes, so the forAdapter() preset
 * turns static methods ON and keeps self/static rewriting. You can also shape the
 * port's vocabulary with methodRenames. The generated adapter forwards instance
 * methods to the adaptee and static methods to the adaptee *class*.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = DeriveConfig::forAdapter()
    ->withMethodRenames(['instanceMethod' => 'doWork']);

$source = DeriveIt::deriveAdapter(_Fixture\StaticHeavyClass::class, config: $config);

show(
    'Adapter for StaticHeavyClass (forAdapter preset + rename)',
    $source,
    'staticPublicMethod() is retained and forwarded to the class; instanceMethod() is emitted as doWork().',
);

$tmp = sys_get_temp_dir() . '/deriveit_cfg_' . md5($source) . '.php';
file_put_contents($tmp, $source);
require $tmp;

echo "\033[1;33mStaticHeavyClassInterface present:\033[0m "
    . (interface_exists(_Fixture\StaticHeavyClassInterface::class, false) ? 'yes' : 'NO') . "\n";
echo "\033[1;33mStaticHeavyClassAdapter present:\033[0m "
    . (class_exists(_Fixture\StaticHeavyClassAdapter::class, false) ? 'yes' : 'NO') . "\n";
