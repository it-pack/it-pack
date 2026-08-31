<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInput\StringInput;

banner('Tree / chunked hashing (NF-D1)');

$tree = NativeHashIt::treeHasher();
$plain = NativeHashIt::create();

$data = str_repeat('x', 200_000); // 200 KB

// Single chunk (chunkSize >= length) collapses to the plain hash() — same digest.
$single = $tree->treeHash(new StringInput($data), 1_000_000, HashAlgorithm::SHA256);
echo 'single-chunk tree hash == hash()? ' . ($single->equals($plain->hash($data, HashAlgorithm::SHA256)) ? 'yes' : 'no') . "\n";

// Multi-chunk: a DISTINCT root digest — this is the point (parallelism / content-defined).
$chunked = $tree->treeHash(new StringInput($data), 64 * 1024, HashAlgorithm::SHA256);
echo 'multi-chunk tree hash: ' . $chunked->value() . "\n";
echo 'multi-chunk differs from hash()? ' . ($chunked->equals($plain->hash($data)) ? 'no' : 'yes') . "\n";

// The chunkSize is part of the function: a different chunkSize => a different root.
$other = $tree->treeHash(new StringInput($data), 32 * 1024, HashAlgorithm::SHA256);
echo 'different chunkSize => different? ' . ($chunked->equals($other) ? 'no' : 'yes') . "\n";

// Deterministic across runs.
$again = $tree->treeHash(new StringInput($data), 64 * 1024, HashAlgorithm::SHA256);
echo 'deterministic? ' . ($chunked->equals($again) ? 'yes' : 'no') . "\n";

echo "\nIMPORTANT: always store the chunkSize next to the digest — it is part of the hash.\n";
echo "Tree hashing is plain-algorithms only (no HMAC); see vertical/26-resumable.php for the\n";
echo "same serialise-and-resume idea applied to plain streaming hashes.\n";
