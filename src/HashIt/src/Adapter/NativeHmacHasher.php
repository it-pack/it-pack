<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use InvalidArgumentException;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInterface;
use ItPack\HashIt\HmacHasherInterface;

final readonly class NativeHmacHasher implements HmacHasherInterface
{
    public function hmac(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        if (!$algorithm instanceof HashAlgorithm) {
            $algorithm = $this->normalize($algorithm);
        }

        if (!$algorithm->isHmacSafe()) {
            throw new InvalidArgumentException('Algorithm is not HMAC-safe: ' . $algorithm->value);
        }

        return new Hash(hash_hmac($algorithm->value, $data, $key, false), $algorithm->value, false);
    }

    private function normalize(string $algorithm): HashAlgorithm
    {
        return HashAlgorithm::tryFrom($algorithm)
            ?? throw new InvalidArgumentException('Unknown hash algorithm: ' . $algorithm);
    }
}
