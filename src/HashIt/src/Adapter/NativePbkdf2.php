<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use InvalidArgumentException;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\Pbkdf2Interface;

/**
 * Native PBKDF2 adapter over the PHP {@see hash_pbkdf2()} extension function.
 *
 * Returns raw (binary) keying material — callers that need a textual encoding
 * (hex/base64) apply it themselves.
 */
final class NativePbkdf2 implements Pbkdf2Interface
{
    public function derive(
        string $password,
        string $salt,
        int $iterations,
        int $length,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): string {
        if ($iterations < 1) {
            throw new InvalidArgumentException('PBKDF2 iterations must be positive');
        }

        if ($length < 1) {
            throw new InvalidArgumentException('PBKDF2 derived key length must be positive');
        }

        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return hash_pbkdf2($algorithm, $password, $salt, $iterations, $length, true);
    }
}
