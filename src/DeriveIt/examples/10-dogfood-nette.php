<?php

declare(strict_types=1);

/*
 * 10 — Dogfooding: derive a real third-party class.
 *
 * The whole point is to derive ports from code you don't own. Here we derive an
 * interface from nette/php-generator's own `ClassType` — proof the tool works on
 * real, complex classes, not just hand-written fixtures.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use Nette\PhpGenerator\ClassType;

$source = DeriveIt::derive(ClassType::class);

show(
    'DeriveIt::derive(' . ClassType::class . ')',
    $source,
    'A self-contained interface for a real, dependency-shipped class.',
);

// The emitted source is valid PHP: load it and confirm the interface exists.
$tmp = sys_get_temp_dir() . '/deriveit_dogfood_' . md5($source) . '.php';
file_put_contents($tmp, $source);
require $tmp;

$interface = 'Nette\\PhpGenerator\\ClassTypeInterface';
echo "\033[1;33mEmitted interface exists:\033[0m "
    . (interface_exists($interface, false) ? 'yes' : 'NO') . "\n";
