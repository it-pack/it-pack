<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;

banner('Password hashing — Argon2id (hash + verify)');

$argon2 = NativeHashIt::argon2();

$password = 's3cret-password';
$hash = $argon2->hash($password);

echo "argon2id hash (self-describing, opaque):\n  " . $hash . "\n";
echo 'verify(correct): ' . ($argon2->verify($password, $hash) ? 'OK' : 'FAIL') . "\n";
echo 'verify(wrong)  : ' . ($argon2->verify('nope', $hash) ? 'OK' : 'FAIL') . "\n";

// Cost parameters (memory/time/threads) are folded into the hash string.
$stronger = $argon2->hash($password, ['memory_cost' => 1 << 17, 'time_cost' => 4]);
echo "custom-cost hash length: " . strlen($stronger) . " chars (params embedded)\n";
echo 'verify against stronger: ' . ($argon2->verify($password, $stronger) ? 'OK' : 'FAIL') . "\n";
