<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Algorithm strength metadata (read-only — never changes hashing behaviour)');

$hasher = NativeHashIt::create();

// info() reads #[AlgorithmMetadata]; isHmacSafe() reads #[HmacSafe]. Both are advisory.
printf("%-12s %-10s %9s %8s  %s\n", 'algo', 'status', 'out(bits)', 'hmac?', 'note');
foreach (['md5', 'sha1', 'sha256', 'sha512', 'sha3-256', 'crc32', 'xxh64'] as $a) {
    $algo = HashAlgorithm::from($a);
    $info = $algo->info();
    $note = '';
    if ($info->isBroken() && $algo->isHmacSafe()) {
        $note = 'broken for collisions, but fine as an HMAC keyed-hash';
    }
    printf(
        "%-12s %-10s %9d %8s  %s\n",
        $algo->value,
        $info->status()->name,
        $info->outputBits(),
        $algo->isHmacSafe() ? 'yes' : 'no',
        $note,
    );
}

// Programmatic policy: hash only with recommended algorithms.
$recommended = HashAlgorithm::recommendedAlgorithms();
$chosen = $recommended[0];
echo "\nPolicy pick (first recommended): " . $chosen->value . ' (' . $chosen->info()->outputBits() . " bits)\n";
echo 'hash with policy algo: ' . $hasher->hash('data', $chosen)->value() . "\n";
