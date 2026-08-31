<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Key derivation — PBKDF2 (iteration-hardened password stretching)');

$pbkdf2 = NativeHashIt::pbkdf2();

$password = 'weak password';
$salt = random_bytes(16);

$t0 = hrtime(true);
$low = $pbkdf2->derive($password, $salt, 1000, 32, HashAlgorithm::SHA256);
$msLow = (hrtime(true) - $t0) / 1_000_000;

$t0 = hrtime(true);
$high = $pbkdf2->derive($password, $salt, 200_000, 32, HashAlgorithm::SHA256);
$msHigh = (hrtime(true) - $t0) / 1_000_000;

echo '1000 iterations   : ' . round($msLow, 1) . " ms\n";
echo '200000 iterations : ' . round($msHigh, 1) . " ms (cost is tunable)\n";
echo 'same input deterministic? ' . ($low === $pbkdf2->derive($password, $salt, 1000, 32) ? 'yes' : 'no') . "\n";
echo 'output length     : ' . strlen($high) . " bytes (raw keying material)\n";
