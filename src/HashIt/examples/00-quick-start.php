<?php

declare(strict_types=1);

require_once __DIR__ . '/_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Quick start — one hasher, many algorithms, content-addressable URNs');

$hasher = NativeHashIt::create();

$data = 'The quick brown fox jumps over the lazy dog';

// Default algorithm is SHA-256; the result is a Hash value object, not a bare string.
$digest = $hasher->hash($data);
echo "SHA-256 (" . strlen($data) . " bytes):\n";
echo '  hex : ' . $digest->value() . "\n";
echo '  algo: ' . $digest->algorithm() . "\n";
echo '  urn : ' . $digest->toUrn() . "   <- stable content-address string\n";

// Pick any algorithm by enum case or by string identifier.
$sha512 = $hasher->hash($data, HashAlgorithm::SHA512);
echo "\nSHA-512: " . $sha512->value() . "\n";

// Timing-safe verification against an expected digest.
$expected = $hasher->hash($data);
echo "\nverify(data, expected)    : " . ($hasher->verify($data, $expected) ? 'match' : 'mismatch') . "\n";
echo "verify(tampered, expected): " . ($hasher->verify($data . '!', $expected) ? 'match' : 'mismatch') . "\n";

// A taste of the keyed path (covered in depth under vertical/20-hmac.php).
$hmac = NativeHashIt::hmacHasher()->hmac($data, 'secret-key');
echo "\nHMAC-SHA256 (keyed): " . $hmac->value() . "\n";

echo "\nThat is the whole surface: build a hasher, hash/verify, get a Hash back.\n";
echo "The horizontal/ and vertical/ folders explore every modality and feature.\n";
