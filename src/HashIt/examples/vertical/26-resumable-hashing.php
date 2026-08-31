<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Resumable (pausable) hashing (NF-D2)');

$r = NativeHashIt::resumable();

// Begin, hash part of the data, then snapshot (checkpoint) the in-progress state.
$handle = $r->start(HashAlgorithm::SHA256);
$handle->update('part one ');
$checkpoint = $handle->checkpoint();

// The live handle keeps working after a checkpoint was taken.
$handle->update('(still live) ');
$live = $handle->finalize();

// Resume in another process from the persisted checkpoint (serialised here to a file).
$cpFile = writeTempFile('checkpoint-26.bin', $checkpoint);
$restored = $r->resume((string) file_get_contents($cpFile));
$restored->update('part two');
$resumed = $restored->finalize();

// Compare against hashing the whole thing in one go.
$whole = NativeHashIt::create()->hash('part one part two');

echo 'live finalize    : ' . $live->value() . "\n";
echo 'resumed finalize : ' . $resumed->value() . "\n";
echo 'resumed == whole (part one part two)? ' . ($resumed->equals($whole) ? 'yes' : 'no') . "\n";
echo 'live == whole?    : ' . ($live->equals($whole) ? 'yes' : 'no') . " (expected: no — live had extra bytes)\n";

// A corrupt/unrelated checkpoint is rejected at resume time.
try {
    $r->resume('not-a-checkpoint');
} catch (\InvalidArgumentException $e) {
    echo 'bad checkpoint rejected: ' . $e->getMessage() . "\n";
}

echo "\nNote: keyed/HMAC contexts cannot be serialised, so resumable is plain-algorithms only.\n";
