<?php

/**
 * This file is auto-generated. Do not change manually. Use: generate_hash_constants_interface.php
 *
 * All algorithms that are available via hash_algos() and hash_hmac_algos() as of PHP 8.1.
 *
 *   Case names are SCREAMING_SNAKE_CASE normalized from the PHP string identifiers:
 *     - '/' replaced with '_'
 *     - '-' replaced with '_'
 *     - ',' replaced with '_'
 *
 *   The string value is the exact identifier accepted by hash_init(), hash(), etc.
 */

declare(strict_types=1);

namespace ItPack\HashIt\src;

interface HashAlgorithms
{
    final public const string MD2 = 'md2';
    final public const string MD4 = 'md4';
    final public const string MD5 = 'md5';
    final public const string SHA1 = 'sha1';
    final public const string SHA224 = 'sha224';
    final public const string SHA256 = 'sha256';
    final public const string SHA384 = 'sha384';
    final public const string SHA512_224 = 'sha512/224';
    final public const string SHA512_256 = 'sha512/256';
    final public const string SHA512 = 'sha512';
    final public const string SHA3_224 = 'sha3-224';
    final public const string SHA3_256 = 'sha3-256';
    final public const string SHA3_384 = 'sha3-384';
    final public const string SHA3_512 = 'sha3-512';
    final public const string RIPEMD128 = 'ripemd128';
    final public const string RIPEMD160 = 'ripemd160';
    final public const string RIPEMD256 = 'ripemd256';
    final public const string RIPEMD320 = 'ripemd320';
    final public const string WHIRLPOOL = 'whirlpool';
    final public const string TIGER128_3 = 'tiger128,3';
    final public const string TIGER160_3 = 'tiger160,3';
    final public const string TIGER192_3 = 'tiger192,3';
    final public const string TIGER128_4 = 'tiger128,4';
    final public const string TIGER160_4 = 'tiger160,4';
    final public const string TIGER192_4 = 'tiger192,4';
    final public const string SNEFRU = 'snefru';
    final public const string SNEFRU256 = 'snefru256';
    final public const string GOST = 'gost';
    final public const string GOST_CRYPTO = 'gost-crypto';
    final public const string ADLER32 = 'adler32';
    final public const string CRC32 = 'crc32';
    final public const string CRC32B = 'crc32b';
    final public const string CRC32C = 'crc32c';
    final public const string FNV132 = 'fnv132';
    final public const string FNV1A32 = 'fnv1a32';
    final public const string FNV164 = 'fnv164';
    final public const string FNV1A64 = 'fnv1a64';
    final public const string JOAAT = 'joaat';
    final public const string MURMUR3A = 'murmur3a';
    final public const string MURMUR3C = 'murmur3c';
    final public const string MURMUR3F = 'murmur3f';
    final public const string XXH32 = 'xxh32';
    final public const string XXH64 = 'xxh64';
    final public const string XXH3 = 'xxh3';
    final public const string XXH128 = 'xxh128';
    final public const string HAVAL128_3 = 'haval128,3';
    final public const string HAVAL160_3 = 'haval160,3';
    final public const string HAVAL192_3 = 'haval192,3';
    final public const string HAVAL224_3 = 'haval224,3';
    final public const string HAVAL256_3 = 'haval256,3';
    final public const string HAVAL128_4 = 'haval128,4';
    final public const string HAVAL160_4 = 'haval160,4';
    final public const string HAVAL192_4 = 'haval192,4';
    final public const string HAVAL224_4 = 'haval224,4';
    final public const string HAVAL256_4 = 'haval256,4';
    final public const string HAVAL128_5 = 'haval128,5';
    final public const string HAVAL160_5 = 'haval160,5';
    final public const string HAVAL192_5 = 'haval192,5';
    final public const string HAVAL224_5 = 'haval224,5';
    final public const string HAVAL256_5 = 'haval256,5';

    final public const array HMAC_SAFE = [
        self::MD2,
        self::MD4,
        self::MD5,
        self::SHA1,
        self::SHA224,
        self::SHA256,
        self::SHA384,
        self::SHA512_224,
        self::SHA512_256,
        self::SHA512,
        self::SHA3_224,
        self::SHA3_256,
        self::SHA3_384,
        self::SHA3_512,
        self::RIPEMD128,
        self::RIPEMD160,
        self::RIPEMD256,
        self::RIPEMD320,
        self::WHIRLPOOL,
        self::TIGER128_3,
        self::TIGER160_3,
        self::TIGER192_3,
        self::TIGER128_4,
        self::TIGER160_4,
        self::TIGER192_4,
        self::SNEFRU,
        self::SNEFRU256,
        self::GOST,
        self::GOST_CRYPTO,
        self::HAVAL128_3,
        self::HAVAL160_3,
        self::HAVAL192_3,
        self::HAVAL224_3,
        self::HAVAL256_3,
        self::HAVAL128_4,
        self::HAVAL160_4,
        self::HAVAL192_4,
        self::HAVAL224_4,
        self::HAVAL256_4,
        self::HAVAL128_5,
        self::HAVAL160_5,
        self::HAVAL192_5,
        self::HAVAL224_5,
        self::HAVAL256_5,
    ];
}
