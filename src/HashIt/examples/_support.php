<?php

declare(strict_types=1);

/**
 * Shared scaffolding for the HashIt examples.
 *
 * Not part of the library: this file only exists so the runnable examples stay
 * short and focused on the API being demonstrated. Every example requires this
 * file (which in turn loads the Composer autoloader) and then calls the helpers
 * below. Copy a single example into your own project together with this file,
 * or just require the Composer autoloader directly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\HashIt\HashAlgorithm;

/**
 * Print a centred-ish section header so example output is easy to scan.
 */
function banner(string $title): void
{
    $rule = str_repeat('=', 72);

    echo "\n" . $rule . "\n " . $title . "\n" . $rule . "\n";
}

/**
 * Write a throwaway file under the system temp dir and delete it on shutdown.
 */
function writeTempFile(string $name, string $contents): string
{
    $path = sys_get_temp_dir() . '/hashit-example-' . $name;
    file_put_contents($path, $contents);

    register_shutdown_function(static fn (): bool => is_file($path) && @unlink($path));

    return $path;
}

/**
 * Create a throwaway directory with a few sample files and delete it on shutdown.
 *
 * @return array{ dir: string, files: list<string> }
 */
function makeTempDir(): array
{
    $dir = sys_get_temp_dir() . '/hashit-example-dir-' . bin2hex(random_bytes(4));
    mkdir($dir);

    $files = [];
    foreach (['alpha.txt' => 'alpha contents', 'beta.txt' => 'beta contents', 'gamma.txt' => 'gamma contents'] as $name => $contents) {
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);
        $files[] = $path;
    }

    register_shutdown_function(static fn (): bool => removeDir($dir));

    return ['dir' => $dir, 'files' => $files];
}

function removeDir(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }

    foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
        @unlink($dir . '/' . $entry);
    }

    return @rmdir($dir);
}

/**
 * Human-readable byte count (e.g. 65536 -> "64 KiB").
 */
function humanBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $unit = ['KiB', 'MiB', 'GiB', 'TiB'];
    $value = $bytes / 1024;
    $i = 0;

    while ($value >= 1024 && $i < count($unit) - 1) {
        $value /= 1024;
        ++$i;
    }

    return round($value, 2) . ' ' . $unit[$i];
}

function recommendedAlgorithms(): array
{
    return HashAlgorithm::recommendedAlgorithms();
}
