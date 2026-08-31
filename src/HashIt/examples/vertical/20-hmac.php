<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;
use ItPack\HashIt\HashAlgorithm;

banner('Keyed / HMAC hashing');

$data = 'the message';
$key = 'shared-secret';

// Minimal one-shot HMAC (no streaming/bulk surface).
$hmac = NativeHashIt::hmacHasher()->hmac($data, $key);
echo 'HMAC-SHA256: ' . $hmac->value() . "\n";

// The full keyed surface mirrors the plain hasher (one-shot, binary, file, verify).
$keyed = NativeHashIt::keyedHasher();
$hash = $keyed->hmac($data, $key, HashAlgorithm::SHA256);
echo 'keyed.hmac      : ' . $hash->value() . "\n";
echo 'keyed.hmacBinary: ' . bin2hex($keyed->hmacBinary($data, $key)->value()) . "\n";

$path = writeTempFile('hmac-20.txt', $data);
echo 'keyed.hmacFile  : ' . $keyed->hmacFile(new Filepath($path), $key)->value() . "\n";
echo 'keyed.verify    : ' . ($keyed->verify($data, $key, $hash) ? 'OK' : 'FAIL') . "\n";

// Determinism: same key+data => identical; a different key => different digest.
$other = NativeHashIt::keyedHasher()->hmac($data, 'other-key');
echo "\nsame key   -> " . ($hash->equals(NativeHashIt::keyedHasher()->hmac($data, $key)) ? 'identical' : 'DIFFER') . "\n";
echo "other key  -> " . ($hash->equals($other) ? 'identical' : 'different') . "\n";

// Only HMAC-safe algorithms are allowed; a checksum like crc32 is rejected.
try {
    NativeHashIt::keyedHasher()->hmac($data, $key, 'crc32');
} catch (\InvalidArgumentException $e) {
    echo 'non-HMAC algorithm rejected: ' . $e->getMessage() . "\n";
}
