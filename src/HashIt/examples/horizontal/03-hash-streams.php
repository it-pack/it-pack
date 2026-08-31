<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashProgress;

banner('Hashing streams — memory-safe, chunked, with progress');

$hasher = NativeHashIt::create();

$text = str_repeat("streamed chunk of data\n", 5000); // ~100 KiB

// A seekable in-memory stream stands in for any readable stream (file, network, …).
$stream = fopen('php://temp', 'r+b');
fwrite($stream, $text);
rewind($stream);

// hashStream() reads in fixed chunks and never materialises the whole payload.
$chunkSize = 8192;
$hash = $hasher->hashStream(
    $stream,
    HashAlgorithm::SHA256,
    $chunkSize,
    HashProgress::fromCallable(static function (int $processed, ?int $total): void {
        $pct = $total === null ? $processed . ' B' : round(100 * $processed / $total) . '%';
        echo "\r  streamed " . $processed . ' B (' . $pct . ')';
    }),
);
echo "\nstream sha256 : " . $hash->value() . "\n";
echo "matches hash(string)? " . ($hash->equals($hasher->hash($text)) ? 'yes' : 'no') . "\n";

fclose($stream);

// Non-seekable streams (e.g. network sockets) are hashed from the current position
// and left where reading stopped — hashStream never assumes it can rewind. (A popen
// pipe is used in real code; we omit it here only to keep the demo warning-free.)
echo "hashStream leaves the caller responsible for stream positioning.\n";
