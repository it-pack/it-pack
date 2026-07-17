<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

/**
 * All algorithms available via hash_algos() as of PHP 8.1.
 *
 * Case names are SCREAMING_SNAKE_CASE normalized from the PHP string identifiers:
 *   - '/' replaced with '_'
 *   - '-' replaced with '_'
 *   - ',' replaced with '_'
 *
 * The string value is the exact identifier accepted by hash_init(), hash(), etc.
 *
 * HMAC-safe algorithms (available via hash_hmac_algos()) can be queried via ::hmacSafe().
 */
enum HashAlgorithm: string
{
    // ── Message digests ──────────────────────────────────────────────────────

    #[HmacSafe]
    case MD2 = HashAlgorithms::MD2;

    #[HmacSafe]
    case MD4 = 'md4';

    #[HmacSafe]
    case MD5 = 'md5';

    #[HmacSafe]
    case SHA1 = 'sha1';

    #[HmacSafe]
    case SHA224 = 'sha224';

    #[HmacSafe]
    case SHA256 = 'sha256';

    #[HmacSafe]
    case SHA384 = 'sha384';

    #[HmacSafe]
    case SHA512_224 = 'sha512/224';

    #[HmacSafe]
    case SHA512_256 = 'sha512/256';

    #[HmacSafe]
    case SHA512 = 'sha512';

    // ── SHA-3 ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    case SHA3_224 = 'sha3-224';

    #[HmacSafe]
    case SHA3_256 = 'sha3-256';

    #[HmacSafe]
    case SHA3_384 = 'sha3-384';

    #[HmacSafe]
    case SHA3_512 = 'sha3-512';

    // ── RIPEMD ───────────────────────────────────────────────────────────────

    #[HmacSafe]
    case RIPEMD128 = 'ripemd128';

    #[HmacSafe]
    case RIPEMD160 = 'ripemd160';

    #[HmacSafe]
    case RIPEMD256 = 'ripemd256';

    #[HmacSafe]
    case RIPEMD320 = 'ripemd320';

    // ── Whirlpool ─────────────────────────────────────────────────────────────

    #[HmacSafe]
    case WHIRLPOOL = 'whirlpool';

    // ── Tiger ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    case TIGER128_3 = 'tiger128,3';

    #[HmacSafe]
    case TIGER160_3 = 'tiger160,3';

    #[HmacSafe]
    case TIGER192_3 = 'tiger192,3';

    #[HmacSafe]
    case TIGER128_4 = 'tiger128,4';

    #[HmacSafe]
    case TIGER160_4 = 'tiger160,4';

    #[HmacSafe]
    case TIGER192_4 = 'tiger192,4';

    // ── Snefru ────────────────────────────────────────────────────────────────

    #[HmacSafe]
    case SNEFRU = 'snefru';

    #[HmacSafe]
    case SNEFRU256 = 'snefru256';

    // ── GOST ──────────────────────────────────────────────────────────────────

    #[HmacSafe]
    case GOST = 'gost';

    #[HmacSafe]
    case GOST_CRYPTO = 'gost-crypto';

    // ── Checksums (NOT HMAC-safe) ─────────────────────────────────────────────

    case ADLER32 = 'adler32';
    case CRC32   = 'crc32';
    case CRC32B  = 'crc32b';
    case CRC32C  = 'crc32c';   // PHP 7.4+

    // ── FNV ───────────────────────────────────────────────────────────────────

    case FNV132  = 'fnv132';
    case FNV1A32 = 'fnv1a32';
    case FNV164  = 'fnv164';
    case FNV1A64 = 'fnv1a64';

    // ── Non-crypto / fast hashes (NOT HMAC-safe) ──────────────────────────────

    case JOAAT    = 'joaat';

    // MurmurHash3 — PHP 8.1+
    case MURMUR3A = 'murmur3a';
    case MURMUR3C = 'murmur3c';
    case MURMUR3F = 'murmur3f';

    // xxHash — PHP 8.1+
    case XXH32  = 'xxh32';
    case XXH64  = 'xxh64';
    case XXH3   = 'xxh3';
    case XXH128 = 'xxh128';

    // ── HAVAL ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    case HAVAL128_3 = 'haval128,3';

    #[HmacSafe]
    case HAVAL160_3 = 'haval160,3';

    #[HmacSafe]
    case HAVAL192_3 = 'haval192,3';

    #[HmacSafe]
    case HAVAL224_3 = 'haval224,3';

    #[HmacSafe]
    case HAVAL256_3 = 'haval256,3';

    #[HmacSafe]
    case HAVAL128_4 = 'haval128,4';

    #[HmacSafe]
    case HAVAL160_4 = 'haval160,4';

    #[HmacSafe]
    case HAVAL192_4 = 'haval192,4';

    #[HmacSafe]
    case HAVAL224_4 = 'haval224,4';

    #[HmacSafe]
    case HAVAL256_4 = 'haval256,4';

    #[HmacSafe]
    case HAVAL128_5 = 'haval128,5';

    #[HmacSafe]
    case HAVAL160_5 = 'haval160,5';

    #[HmacSafe]
    case HAVAL192_5 = 'haval192,5';

    #[HmacSafe]
    case HAVAL224_5 = 'haval224,5';

    #[HmacSafe]
    case HAVAL256_5 = 'haval256,5';

    // ── Convenience ───────────────────────────────────────────────────────────

    /**
     * Returns true when this algorithm is accepted by hash_hmac().
     */
    public function isHmacSafe(): bool
    {
        return in_array($this->name, self::hmacSafeAlgorithms(), true);
    }

    /**
     * All HMAC-safe algorithms.
     *
     * @return list<self>
     */
    public static function hmacSafeAlgorithms(): array
    {
        static $hmacSafe;
        return $hmacSafe ?: HashAlgorithms::HMAC_SAFE;
    }

    /**
     * Suggested default for content-addressing use cases.
     * Secure, widely supported, URN-friendly.
     */
    public static function default(): self
    {
        return self::SHA256;
    }

}