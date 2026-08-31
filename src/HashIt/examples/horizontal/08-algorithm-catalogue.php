<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Algorithm catalogue: discover, check, resolve');

$hasher = NativeHashIt::create();

$all = $hasher->algorithms();
echo 'catalogue size : ' . count($all) . " algorithms\n";
echo "supports('sha3-256')? " . ($hasher->supports('sha3-256') ? 'yes' : 'no') . "\n";
echo "supports('nope')?     " . ($hasher->supports('nope') ? 'yes' : 'no') . "\n";

// resolve() accepts the enum VALUE ("sha256") or the SCREAMING enum NAME ("SHA256").
$byValue = $hasher->resolve('sha256');
$byName = $hasher->resolve('SHA256');
echo "resolve('sha256') === resolve('SHA256')? "
    . ($byValue === $byName ? 'yes' : 'no') . ' (' . $byValue->name . ")\n";

// Resolving an unknown identifier fails closed.
try {
    $hasher->resolve('not-a-hash');
} catch (\InvalidArgumentException $e) {
    echo "resolve('not-a-hash') throws: " . $e->getMessage() . "\n";
}

// Convenience subsets shipped on the enum.
echo "\nHMAC-safe algorithms : " . count(HashAlgorithm::hmacSafeAlgorithms()) . "\n";
echo "Recommended algorithms: " . count(HashAlgorithm::recommendedAlgorithms()) . "\n";
echo "Broken algorithms     : " . count(HashAlgorithm::brokenAlgorithms()) . " (md5, sha1, …)\n";
