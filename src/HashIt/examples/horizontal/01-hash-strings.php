<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

banner('Hashing strings — algorithms, hex vs raw, and digest length');

$hasher = NativeHashIt::create();
$data = 'hash me';

// Algorithm may be omitted (defaults to SHA-256) or given as an enum / string.
$default = $hasher->hash($data);
$byEnum = $hasher->hash($data, HashAlgorithm::SHA256);
$byString = $hasher->hash($data, 'sha256');
echo 'default == enum == string id ? '
    . ($default->equals($byEnum) && $byEnum->equals($byString) ? 'yes' : 'no') . "\n";

// Loop over several algorithms to see digest sizes differ.
echo "\nAlgorithm        digest (hex)                          output bits\n";
foreach (['sha1', 'sha256', 'sha512', 'sha3-256', 'xxh64', 'crc32'] as $algo) {
    $h = $hasher->hash($data, $algo);
    printf("  %-12s %-36s %d\n", $h->algorithm(), $h->value(), $h->length() * 4);
}

// hash() returns hex; hashBinary() returns the raw digest bytes (half the length).
$hex = $hasher->hash($data, HashAlgorithm::SHA256);
$raw = $hasher->hashBinary($data, HashAlgorithm::SHA256);
echo "\nSHA-256 hex length : " . $hex->length() . " bytes (human: " . ($hex->length() * 4) . " bits)\n";
echo "SHA-256 raw length : " . $raw->length() . " bytes (the real digest size)\n";

// The two representations of the SAME digest share one content-address URN.
echo "hex urn : " . $hex->toUrn() . "\n";
echo "raw urn : " . $raw->toUrn() . "\n";
echo "same URN? " . ($hex->toUrn() === $raw->toUrn() ? 'yes' : 'no') . "\n";
