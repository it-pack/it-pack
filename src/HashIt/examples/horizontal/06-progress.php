<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashProgress;

banner('Progress reporting on large payloads');

$hasher = NativeHashIt::create();

$size = 2 * 1024 * 1024; // 2 MiB of deterministic data
$data = str_repeat('ABCDEFGHIJ', (int) ($size / 10));

$stream = fopen('php://temp', 'r+b');
fwrite($stream, $data);
rewind($stream);

$last = -1;
$hash = $hasher->hashStream(
    $stream,
    HashAlgorithm::SHA256,
    64 * 1024,
    HashProgress::fromCallable(static function (int $processed, ?int $total) use (&$last): void {
        $pct = $total === null ? $processed : (int) round(100 * $processed / $total);
        if ($pct !== $last) {
            $last = $pct;
            echo "\r  hashing… {$pct}%  (" . $processed . ' B)';
        }
    }),
);
echo "\nfinal sha256: " . $hash->value() . "\n";

fclose($stream);
