<?php

declare(strict_types=1);

/*
 * Shared helpers for the CommitIt examples.
 *
 * Run any example with: php examples/NN_*.php
 * Each example is self-contained: it creates temporary git repositories under
 * the system temp directory and prints a human-readable narrative.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use ItPack\CommitIt\Domain\Value\RepositoryPath;

function exampleTempDir(string $name): string
{
    $base = sys_get_temp_dir() . '/commitit-examples';

    if (!is_dir($base)) {
        mkdir($base, 0o755, true);
    }

    $dir = $base . '/' . $name . '-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6);

    mkdir($dir, 0o755, true);

    return $dir;
}

function headline(string $text): void
{
    echo "\n\033[1;36m" . $text . "\033[0m\n";
    echo str_repeat('=', strlen($text)) . "\n";
}

function step(string $text): void
{
    echo "\033[0;33m•\033[0m " . $text . "\n";
}

function success(string $text): void
{
    echo "\033[0;32m✓\033[0m " . $text . "\n";
}

function fail(string $text): void
{
    echo "\033[0;31m✗\033[0m " . $text . "\n";
}

function formatBranches(array $branches): string
{
    return implode(', ', array_map(static fn (object|string $b): string => (string) $b, $branches));
}
