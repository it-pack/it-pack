<?php

declare(strict_types=1);

namespace ItPack\HashIt\KeyDerivation;

use InvalidArgumentException;
use ItPack\HashIt\Contract\KeyDerivationInterface;
use ItPack\HashIt\Contract\KeyedHasherInterface;
use ItPack\HashIt\HashAlgorithm;

/**
 * RFC 5869 HKDF (Extract-and-Expand) built solely on the HMAC surface exposed by
 * {@see KeyedHasherInterface}, so it lives in core and never touches a native
 * hashing extension directly.
 *
 *   Extract:  PRK = HMAC-Hash(salt, IKM)
 *   Expand:   T(0) = ''
 *             T(i) = HMAC-Hash(PRK, T(i-1) | info | i)   // i is a single counter byte
 *             OKM  = T(1) | T(2) | ... , truncated to $length
 *
 * The output is capped at 255 * HashLen octets (RFC 5869 hard limit).
 */
final readonly class Hkdf implements KeyDerivationInterface
{
    public function __construct(
        private KeyedHasherInterface $hasher,
    ) {
    }

    public function derive(
        string $ikm,
        ?string $salt = null,
        int $length = 32,
        string $info = '',
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): string {
        if ($length < 1) {
            throw new InvalidArgumentException('Derived key length must be positive');
        }

        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm : HashAlgorithm::from($algorithm);
        $hashLen = $this->hashLen($algorithm);

        if ($length > 255 * $hashLen) {
            throw new InvalidArgumentException(sprintf(
                'Derived key length %d exceeds the RFC 5869 maximum of %d octets for %s',
                $length,
                255 * $hashLen,
                $algorithm->value,
            ));
        }

        // PHP's hash_hmac rejects an empty HMAC key, and RFC 5869 treats a
        // missing salt as HashLen zero octets — so both an omitted and an
        // explicitly empty salt collapse to the RFC default rather than crash.
        $saltKey = ($salt === null || $salt === '') ? str_repeat("\0", $hashLen) : $salt;
        $prk = $this->hasher->hmacBinary($ikm, $saltKey, $algorithm)->value();

        $output = '';
        $block = '';
        $counter = 1;

        while (strlen($output) < $length) {
            $block = $this->hasher->hmacBinary($block . $info . chr($counter), $prk, $algorithm)->value();
            $output .= $block;
            ++$counter;
        }

        return substr($output, 0, $length);
    }

    private function hashLen(HashAlgorithm $algorithm): int
    {
        $bits = $algorithm->info()->outputBits();

        return $bits > 0 ? (int) ($bits / 8) : 32;
    }
}
