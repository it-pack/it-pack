<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;

banner('The Hash value object: equality, metadata, content-address URNs');

$hasher = NativeHashIt::create();

$hex = $hasher->hash('hello', HashAlgorithm::SHA256);
$raw = $hasher->hashBinary('hello', HashAlgorithm::SHA256);

echo 'hex value  : ' . $hex->value() . "\n";
echo 'hex algo   : ' . $hex->algorithm() . "\n";
echo 'hex binary?: ' . ($hex->binary() ? 'yes' : 'no') . "\n";
echo 'hex length : ' . $hex->length() . " bytes\n";

// equals() compares algorithm + binary flag + digest, in constant time.
$again = $hasher->hash('hello', HashAlgorithm::SHA256);
echo "\nequals(same content)       : " . ($hex->equals($again) ? 'yes' : 'no') . "\n";
echo "equals(other content)     : " . ($hex->equals($hasher->hash('world')) ? 'yes' : 'no') . "\n";
echo "equals(raw of same digest): " . ($hex->equals($raw) ? 'yes' : 'no') . " (expected: yes)\n";

// The URN is a stable content address — independent of hex vs binary encoding.
echo "\nurn(hex): " . $hex->toUrn() . "\n";
echo "urn(raw): " . $raw->toUrn() . "\n";

// Reconstruct a Hash from a stored representation (e.g. read back from a database).
$stored = Hash::create($hex->value(), 'sha256');
echo "\nHash::create(hex, 'sha256') equals original? " . ($stored->equals($hex) ? 'yes' : 'no') . "\n";
