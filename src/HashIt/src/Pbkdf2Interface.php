<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Password-based key derivation port (PBKDF2 shape). Unlike HKDF this is
 * iteration-hardened for stretching weak, human-chosen secrets — the two KDF
 * families solve different problems, so the surface deliberately differs from
 * {@see KeyDerivationInterface}.
 */
interface Pbkdf2Interface
{
    public function derive(
        string $password,
        string $salt,
        int $iterations,
        int $length,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): string;
}
