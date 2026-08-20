<?php

declare(strict_types=1);

/**
 * Demo: derive a self-contained interface from a class and print the source.
 * Run from the project root: php scripts/derive.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\DeriveIt\DeriveIt;

$target = \_Fixture\UnionTypeClass::class;

echo DeriveIt::derive($target), PHP_EOL;
