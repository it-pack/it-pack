<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use InvalidArgumentException;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use ItPack\HashIt\AlgorithmSourceInterface;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInterface;
use ItPack\HashIt\HmacHasherInterface;

final readonly class NativeHmacHasher implements HmacHasherInterface
{
    public function __construct(
        private ?AlgorithmSourceInterface $source = null,
    ) {
    }

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

    public function algorithms(): array
    {
        return $this->source()->algorithms();
    }

    public function supports(string $identifier): bool
    {
        return $this->source()->supports($identifier);
    }

    public function resolve(string $identifier): HashAlgorithm
    {
        return $this->source()->resolve($identifier);
    }

    private function source(): AlgorithmSourceInterface
    {
        return $this->source ?? new NativeAlgorithmSource();
    }

    private function normalize(string $algorithm): HashAlgorithm
    {
        return HashAlgorithm::tryFrom($algorithm)
            ?? throw new InvalidArgumentException('Unknown hash algorithm: ' . $algorithm);
    }
}
