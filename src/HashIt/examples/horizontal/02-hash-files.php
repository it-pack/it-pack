<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;

banner('Hashing files');

$hasher = NativeHashIt::create();

$path = writeTempFile('file-02.txt', "line one\nline two\n");

// hashFile() accepts a FilepathInterface (the core Filepath is constructible directly).
$hash = $hasher->hashFile(new Filepath($path));
echo "file   : $path\n";
echo "sha256 : " . $hash->value() . "\n";

// Hashing the file == hashing the same bytes as a string.
echo "same as hash(string)? " . ($hash->equals($hasher->hash((string) file_get_contents($path))) ? 'yes' : 'no') . "\n";

// Any algorithm works on files too.
echo "sha3-256: " . $hasher->hashFile(new Filepath($path), 'sha3-256')->value() . "\n";
echo "xxh64   : " . $hasher->hashFile(new Filepath($path), 'xxh64')->value() . "\n";

// A missing/unreadable file fails closed with a RuntimeException.
try {
    $hasher->hashFile(new Filepath($path . '.does-not-exist'));
} catch (\RuntimeException $e) {
    echo "\nmissing file throws: " . $e->getMessage() . "\n";
}
