<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * A single expected-vs-actual mismatch produced while verifying a {@see HashManifest}.
 */
final readonly class HashMismatch
{
    public function __construct(
        public string $path,
        public Contract\HashInterface $expected,
        public Contract\HashInterface $actual,
    ) {
    }
}
