<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

$hasher = NativeHashIt::create();

echo "Hashing 'The quick brown fox':\n";
foreach (['sha256', 'sha512', 'md5'] as $algo) {
    $hash = $hasher->hash('The quick brown fox', $algo);
    printf("  %-8s %s\n", $hash->algorithm(), $hash->value());
}

$binary = $hasher->hashBinary('The quick brown fox', HashAlgorithm::SHA256);
echo '  binary sha256 byte length: ' . strlen($binary->value()) . "\n";
