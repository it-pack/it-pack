<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use InvalidArgumentException;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashContextInterface;
use ItPack\HashIt\KeyedContextFactoryInterface;

final readonly class NativeKeyedContextFactory implements KeyedContextFactoryInterface
{
    public function build(string|HashAlgorithm $algorithm, string $key): HashContextInterface
    {
        if (!$algorithm instanceof HashAlgorithm) {
            $algorithm = HashAlgorithm::tryFrom($algorithm)
                ?? throw new InvalidArgumentException('Unknown hash algorithm: ' . $algorithm);
        }

        if (!$algorithm->isHmacSafe()) {
            throw new InvalidArgumentException('Algorithm is not HMAC-safe: ' . $algorithm->value);
        }

        return new NativeHashContext(hash_init($algorithm->value, HASH_HMAC, $key));
    }
}
