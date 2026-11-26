#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * tools/example_test.php — CI smoke test for the DisenchantIt examples.
 *
 * Runs every example in examples/ (except bootstrap.php) and asserts each
 * exits with code 0. Intended as a CI smoke test: if any example breaks,
 * this script exits non-zero.
 *
 * Run: php tools/smoke_test.php
 */

$examplesDir = __DIR__ . '/../examples';

$files = "{$examplesDir}/*.php"
            |> glob(...)
            |> (static fn($x) => array_filter($x, fn(string $path): bool => basename($path) !== 'bootstrap.php',))
            |> array_values(...);

sort($files);

if ($files === []) {
    fwrite(STDERR, "No example files found.\n");
    exit(1);
}

$failures = [];
$passed = 0;

foreach ($files as $file) {
    $command = sprintf('php %s', escapeshellarg($file));
    $exitCode = null;
    $output = [];
    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[basename($file)] = [
            'exit' => $exitCode,
            'output' => implode("\n", $output),
        ];
        continue;
    }

    $passed++;
    echo '✓ ' . basename($file) . PHP_EOL;
}

echo PHP_EOL;
echo sprintf('Smoke test: %d/%d examples passed', $passed, count($files)) . PHP_EOL;

if ($failures !== []) {
    echo PHP_EOL . 'FAILURES:' . PHP_EOL;
    foreach ($failures as $name => $failure) {
        echo "  • {$name} (exit {$failure['exit']}):" . PHP_EOL;
        echo implode("\n    ", $failure['output']) . PHP_EOL;
    }
    exit(1);
}

echo 'All examples pass.' . PHP_EOL;
