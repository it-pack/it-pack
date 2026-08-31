<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashProgress;

banner('Hashing many files and whole directories');

$hasher = NativeHashIt::create();

$tmp = makeTempDir();

// hashFiles() takes any iterable of FilepathInterface and returns path => Hash.
$map = $hasher->hashFiles(array_map(
    static fn (string $p): Filepath => new Filepath($p),
    $tmp['files'],
));

echo 'hashFiles() over ' . count($map) . " files:\n";
foreach ($map as $path => $hash) {
    echo '  ' . basename($path) . ' -> ' . substr($hash->value(), 0, 16) . "...\n";
}

// hashDirectory() scans a directory: regular files only, sorted by path.
$dirMap = $hasher->hashDirectory($tmp['dir']);
echo "\nhashDirectory() found " . count($dirMap) . " files (same set, sorted):\n";
echo '  ' . implode(', ', array_map('basename', array_keys($dirMap))) . "\n";

// Bulk hashing also reports progress after each file.
echo "\nWith progress callback:\n";
$hasher->hashFiles(
    array_map(static fn (string $p): Filepath => new Filepath($p), $tmp['files']),
    HashAlgorithm::SHA256,
    HashProgress::fromCallable(static function (int $processed, ?int $total): void {
        echo "\r  " . $processed . '/' . ($total ?? '?') . ' B';
    }),
);
echo "\n";
