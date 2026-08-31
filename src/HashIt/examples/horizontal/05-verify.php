<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Verifying data against an expected digest (timing-safe)');

$hasher = NativeHashIt::create();

$secret = 'correct horse battery staple';
$expected = $hasher->hash($secret, HashAlgorithm::SHA256);

echo 'expected sha256: ' . $expected->value() . "\n";
echo 'verify(good)      : ' . ($hasher->verify($secret, $expected) ? 'OK' : 'FAIL') . "\n";
echo 'verify(attacker)   : ' . ($hasher->verify('attacker guess', $expected) ? 'OK' : 'FAIL') . "\n";

// Binary digests verify with verifyBinary().
$raw = $hasher->hashBinary($secret, HashAlgorithm::SHA256);
echo 'verifyBinary(good): ' . ($hasher->verifyBinary($secret, $raw) ? 'OK' : 'FAIL') . "\n";

// A digest from a different algorithm never matches the same bytes.
$sha512 = $hasher->hash($secret, HashAlgorithm::SHA512);
echo 'verify with SHA-512 digest of same bytes: '
    . ($hasher->verify($secret, $sha512) ? 'OK' : 'FAIL') . " (expected: FAIL)\n";
