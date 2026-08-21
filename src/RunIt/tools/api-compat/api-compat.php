<?php

declare(strict_types=1);

namespace ItPack\RunIt\Tool\ApiCompat;

use Tools\ApiCompat\ApiCompat;

use function dirname;

$autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (is_file($autoloader)) {
    require $autoloader;
}

$argv = $_SERVER['argv'] ?? [];

try {
    $tool = new ApiCompat($argv);

    exit($tool->run());
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;

    exit(1);
}
