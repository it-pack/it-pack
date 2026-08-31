<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInput\CompositeInput;
use ItPack\HashIt\HashInput\StringInput;

banner('Structured / composite hashing (deterministic framing)');

$hasher = NativeHashIt::create();

// Compose ordered parts; the framing makes the digest depend on order AND boundaries.
$d1 = $hasher->hashInput(new CompositeInput([
    new StringInput('ab'),
    new StringInput('c'),
]), HashAlgorithm::SHA256);

// 'a' + 'bc' has the same bytes but a different framing => a different digest.
$d2 = $hasher->hashInput(new CompositeInput([
    new StringInput('a'),
    new StringInput('bc'),
]), HashAlgorithm::SHA256);

echo 'hashInput([ab, c]) : ' . $d1->value() . "\n";
echo 'hashInput([a, bc])  : ' . $d2->value() . "\n";
echo 'framing matters (different)? ' . ($d1->equals($d2) ? 'NO (?!)' : 'yes') . "\n";

// The same composition always digests identically, however it was built.
$again = $hasher->hashInput(new CompositeInput([
    new StringInput('ab'),
    new StringInput('c'),
]), HashAlgorithm::SHA256);
echo 'deterministic (same order)? ' . ($d1->equals($again) ? 'yes' : 'no') . "\n";

// Contrast: concatenating the bytes first has NO framing, so it digests differently.
echo 'hash("abc") (no framing)     : ' . $hasher->hash('abc')->value() . "\n";
echo 'composite == plain concat?   : ' . ($d1->equals($hasher->hash('abc')) ? 'yes' : 'no') . " (expected: no)\n";

echo "\nUse case: hash a structured record as one stable digest regardless of how\n";
echo "the parts were assembled — order and chunk boundaries are pinned by the framing.\n";
