<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use ReflectionEnumUnitCase;

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
 * Strength metadata (collision resistance rating + digest size) is attached via
 * #[AlgorithmMetadata] and read through ::info() — this is read-only and changes
 * no hashing behaviour. HMAC-safety and collision-strength are orthogonal.
 */
enum HashAlgorithm: string
{
    // ── Message digests ──────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 128, status: AlgorithmStatus::Broken)]
    case MD2 = 'md2';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 512, status: AlgorithmStatus::Broken)]
    case MD4 = 'md4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 512, status: AlgorithmStatus::Broken)]
    case MD5 = 'md5';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 512, status: AlgorithmStatus::Broken)]
    case SHA1 = 'sha1';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 512, status: AlgorithmStatus::Recommended)]
    case SHA224 = 'sha224';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 512, status: AlgorithmStatus::Recommended)]
    case SHA256 = 'sha256';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 384, blockBits: 1024, status: AlgorithmStatus::Recommended)]
    case SHA384 = 'sha384';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 1024, status: AlgorithmStatus::Recommended)]
    case SHA512_224 = 'sha512/224';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 1024, status: AlgorithmStatus::Recommended)]
    case SHA512_256 = 'sha512/256';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 512, blockBits: 1024, status: AlgorithmStatus::Recommended)]
    case SHA512 = 'sha512';

    // ── SHA-3 ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 1152, status: AlgorithmStatus::Recommended)]
    case SHA3_224 = 'sha3-224';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 1088, status: AlgorithmStatus::Recommended)]
    case SHA3_256 = 'sha3-256';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 384, blockBits: 832, status: AlgorithmStatus::Recommended)]
    case SHA3_384 = 'sha3-384';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 512, blockBits: 576, status: AlgorithmStatus::Recommended)]
    case SHA3_512 = 'sha3-512';

    // ── RIPEMD ───────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case RIPEMD128 = 'ripemd128';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case RIPEMD160 = 'ripemd160';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case RIPEMD256 = 'ripemd256';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 320, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case RIPEMD320 = 'ripemd320';

    // ── Whirlpool ─────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 512, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case WHIRLPOOL = 'whirlpool';

    // ── Tiger ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER128_3 = 'tiger128,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER160_3 = 'tiger160,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 192, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER192_3 = 'tiger192,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER128_4 = 'tiger128,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER160_4 = 'tiger160,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 192, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case TIGER192_4 = 'tiger192,4';

    // ── Snefru ────────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case SNEFRU = 'snefru';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 512, status: AlgorithmStatus::Legacy)]
    case SNEFRU256 = 'snefru256';

    // ── GOST ──────────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 256, status: AlgorithmStatus::Legacy)]
    case GOST = 'gost';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 256, status: AlgorithmStatus::Legacy)]
    case GOST_CRYPTO = 'gost-crypto';

    // ── Checksums (NOT HMAC-safe) ─────────────────────────────────────────────

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case ADLER32 = 'adler32';

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case CRC32   = 'crc32';

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case CRC32B  = 'crc32b';

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case CRC32C  = 'crc32c';   // PHP 7.4+

    // ── FNV ───────────────────────────────────────────────────────────────────

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case FNV132  = 'fnv132';

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case FNV1A32 = 'fnv1a32';

    #[AlgorithmMetadata(outputBits: 64, blockBits: 0, status: AlgorithmStatus::Weak)]
    case FNV164  = 'fnv164';

    #[AlgorithmMetadata(outputBits: 64, blockBits: 0, status: AlgorithmStatus::Weak)]
    case FNV1A64 = 'fnv1a64';

    // ── Non-crypto / fast hashes (NOT HMAC-safe) ──────────────────────────────

    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case JOAAT    = 'joaat';

    // MurmurHash3 — PHP 8.1+
    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case MURMUR3A = 'murmur3a';

    #[AlgorithmMetadata(outputBits: 128, blockBits: 0, status: AlgorithmStatus::Weak)]
    case MURMUR3C = 'murmur3c';

    #[AlgorithmMetadata(outputBits: 128, blockBits: 0, status: AlgorithmStatus::Weak)]
    case MURMUR3F = 'murmur3f';

    // xxHash — PHP 8.1+
    #[AlgorithmMetadata(outputBits: 32, blockBits: 0, status: AlgorithmStatus::Weak)]
    case XXH32  = 'xxh32';

    #[AlgorithmMetadata(outputBits: 64, blockBits: 0, status: AlgorithmStatus::Weak)]
    case XXH64  = 'xxh64';

    #[AlgorithmMetadata(outputBits: 64, blockBits: 0, status: AlgorithmStatus::Weak)]
    case XXH3   = 'xxh3';

    #[AlgorithmMetadata(outputBits: 128, blockBits: 0, status: AlgorithmStatus::Weak)]
    case XXH128 = 'xxh128';

    // ── HAVAL ─────────────────────────────────────────────────────────────────

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL128_3 = 'haval128,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL160_3 = 'haval160,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 192, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL192_3 = 'haval192,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL224_3 = 'haval224,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL256_3 = 'haval256,3';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL128_4 = 'haval128,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL160_4 = 'haval160,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 192, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL192_4 = 'haval192,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL224_4 = 'haval224,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL256_4 = 'haval256,4';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 128, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL128_5 = 'haval128,5';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 160, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL160_5 = 'haval160,5';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 192, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL192_5 = 'haval192,5';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 224, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL224_5 = 'haval224,5';

    #[HmacSafe]
    #[AlgorithmMetadata(outputBits: 256, blockBits: 1024, status: AlgorithmStatus::Legacy)]
    case HAVAL256_5 = 'haval256,5';

    // ── Convenience ───────────────────────────────────────────────────────────

    /**
     * Returns true when this algorithm is accepted by hash_hmac().
     */
    public function isHmacSafe(): bool
    {
        return in_array($this, self::hmacSafeAlgorithms(), true);
    }

    /**
     * All HMAC-safe algorithms, derived from the #[HmacSafe] attribute so the
     * catalogue lives in exactly one place (the enum cases above).
     *
     * @return list<self>
     */
    public static function hmacSafeAlgorithms(): array
    {
        static $hmacSafe;
        if (isset($hmacSafe)) {
            return $hmacSafe;
        }

        $hmacSafe = [];
        foreach (self::cases() as $case) {
            $reflection = new ReflectionEnumUnitCase(self::class, $case->name);
            if ($reflection->getAttributes(HmacSafe::class) !== []) {
                $hmacSafe[] = $case;
            }
        }

        return $hmacSafe;
    }

    /**
     * Read-only strength metadata for this algorithm.
     *
     * Derived from the #[AlgorithmMetadata] attribute (memoized per case).
     * Falls back to a conservative Legacy/unknown placeholder when a case is
     * somehow unannotated, so the API never throws for missing metadata.
     */
    public function info(): AlgorithmInfo
    {
        static $cache = [];

        if (isset($cache[$this->name])) {
            return $cache[$this->name];
        }

        $metadata = self::metadataFor($this)
            ?? new AlgorithmMetadata(outputBits: 0, blockBits: 0, status: AlgorithmStatus::Legacy);

        return $cache[$this->name] = new AlgorithmInfo(
            outputBits: $metadata->outputBits,
            blockBits: $metadata->blockBits,
            status: $metadata->status,
        );
    }

    /**
     * Algorithms rated Recommended for cryptographic collision resistance.
     *
     * @return list<self>
     */
    public static function recommendedAlgorithms(): array
    {
        return self::casesWithStatus(AlgorithmStatus::Recommended);
    }

    /**
     * Algorithms whose collision resistance is practically broken (e.g. MD5, SHA1).
     *
     * Note: these may still be HMAC-safe and usable as keyed-hash functions.
     *
     * @return list<self>
     */
    public static function brokenAlgorithms(): array
    {
        return self::casesWithStatus(AlgorithmStatus::Broken);
    }

    /**
     * @return list<self>
     */
    private static function casesWithStatus(AlgorithmStatus $status): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            if ($case->info()->status() === $status) {
                $result[] = $case;
            }
        }

        return $result;
    }

    private static function metadataFor(self $case): ?object
    {
        $reflection = new ReflectionEnumUnitCase(self::class, $case->name);
        $attributes = $reflection->getAttributes(AlgorithmMetadata::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
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
