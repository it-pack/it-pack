<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Key derivation — HKDF (RFC 5869, Extract-and-Expand)');

$kdf = NativeHashIt::kdf();

$ikm = 'input keying material';
$salt = 'salt';
$info = 'application label';

$okm1 = $kdf->derive($ikm, $salt, 32, $info, HashAlgorithm::SHA256);
$okm2 = $kdf->derive($ikm, $salt, 32, $info, HashAlgorithm::SHA256);
echo 'derived (32 bytes): ' . bin2hex($okm1) . "\n";
echo 'deterministic? ' . ($okm1 === $okm2 ? 'yes' : 'no') . "\n";

// The info label binds the derived key to a purpose; a different label => different key.
$okmInfo = $kdf->derive($ikm, $salt, 32, 'other label');
echo "different info => different? " . ($okm1 !== $okmInfo ? 'yes' : 'no') . "\n";

// So does a different salt.
$okmSalt = $kdf->derive($ikm, 'different-salt', 32, $info);
echo "different salt => different? " . ($okm1 !== $okmSalt ? 'yes' : 'no') . "\n";

// HKDF caps output at 255 * HashLen octets (8160 for SHA-256); more is rejected.
try {
    $kdf->derive($ikm, $salt, 256 * 32 + 1, $info);
} catch (\InvalidArgumentException $e) {
    echo 'over-length rejected: ' . $e->getMessage() . "\n";
}
