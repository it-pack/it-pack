<?php

declare(strict_types=1);

/*
 * 15 — Dogfood: an adapter for a real third-party class.
 *
 * Like example 10 but emit an adapter. Deriving an interface AND a delegating adapter
 * for nette/php-generator's ClassType proves the tool produces valid, loadable,
 * instantiable PHP from a real dependency — not just fixtures.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use Nette\PhpGenerator\ClassType;

$source = DeriveIt::deriveAdapter(ClassType::class);

show(
    'DeriveIt::deriveAdapter(' . ClassType::class . ')',
    $source,
    'Interface + delegating adapter for a real, shipped class.',
);

$tmp = sys_get_temp_dir() . '/deriveit_dogfood_adapter_' . md5($source) . '.php';
file_put_contents($tmp, $source);
require $tmp;

$interface = 'Nette\\PhpGenerator\\ClassTypeInterface';
$adapter = 'Nette\\PhpGenerator\\ClassTypeAdapter';

echo "\033[1;33mEmitted interface exists:\033[0m " . (interface_exists($interface, false) ? 'yes' : 'NO') . "\n";
echo "\033[1;33mEmitted adapter exists:\033[0m " . (class_exists($adapter, false) ? 'yes' : 'NO') . "\n";

$adapterInstance = new $adapter(new ClassType('Widget'));
echo "\033[1;33mAdapter wraps a real instance:\033[0m " . get_debug_type($adapterInstance) . "\n";
