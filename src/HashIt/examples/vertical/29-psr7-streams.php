<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\StreamHasherInterface;
use ItPack\HashIt\HashAlgorithm;
use Nyholm\Psr7\Stream;

banner('PSR-7 integration — hash a PSR-7 StreamInterface without loading it all');

if (!interface_exists(\Psr\Http\Message\StreamInterface::class)) {
    echo "psr/http-message not installed; skipping.\n";
    return;
}

// A PSR-7 body stream stands in for an HTTP request/response body.
$body = Stream::create(str_repeat('request body chunk', 1000));

$hasher = NativeHashIt::streamHasher();
$hash = $hasher->hash($body, HashAlgorithm::SHA256);

echo 'PSR-7 stream sha256: ' . $hash->value() . "\n";
echo 'stream still readable afterwards? ' . ($body->isReadable() ? 'yes' : 'no') . "\n";
echo 're-readable size: ' . $body->getSize() . " bytes (the body was never detached)\n";

echo "\nThe same request body can therefore be hashed AND forwarded downstream.\n";
