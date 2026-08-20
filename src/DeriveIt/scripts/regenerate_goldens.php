<?php

declare(strict_types=1);

/**
 * Dev helper: regenerate the golden interface fixtures in tests/_expected.
 * Run from the project root: php scripts/regenerate_goldens.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\DeriveIt\DeriveIt;
use ReflectionClass;

$roots = ['_Fixture' => 'fixtures', '_Fixture\Stubs' => 'fixtures/Stubs'];
$outDir = __DIR__ . '/../tests/_expected';
@mkdir($outDir, 0777, true);

$count = 0;
foreach ($roots as $namespace => $dir) {
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $class = '\\' . $namespace . '\\' . basename($file, '.php');

        if (!class_exists($class, true)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum()) {
            continue;
        }

        file_put_contents($outDir . '/' . $reflection->getShortName() . '.interface.php', DeriveIt::derive($reflection));
        $count++;
    }
}

echo "Regenerated {$count} golden interface files in {$outDir}\n";
