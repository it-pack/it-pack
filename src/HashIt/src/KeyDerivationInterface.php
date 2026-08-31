<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Key derivation port, HKDF/PBKDF2-shaped: turn input keying material (plus an
 * optional salt and info label) into a uniformly sized output of arbitrary
 * length. The headline implementation is {@see \ItPack\HashIt\KeyDerivation\Hkdf},
 * which derives purely from {@see KeyedHasherInterface} (RFC 5869).
 *
 * `salt` is optional: when omitted it defaults to a string of HashLen zero
 * octets, exactly as RFC 5869 §3.1 prescribes, so a zero-length salt and a
 * *missing* salt are deliberately distinct (the former is an explicit empty
 * key, the latter the RFC default).
 */
interface KeyDerivationInterface
{
    public function derive(
        string $ikm,
        ?string $salt = null,
        int $length = 32,
        string $info = '',
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): string;
}
