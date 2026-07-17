<?php

/**
 * This file is auto-generated. Do not change manually. Use: generate_hash_enum.php
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

enum HashAlgorithmEnum: string
{
    case MD2 = HashAlgorithms::MD2;
    case MD4 = HashAlgorithms::MD4;
    case MD5 = HashAlgorithms::MD5;
    case SHA1 = HashAlgorithms::SHA1;
    case SHA224 = HashAlgorithms::SHA224;
    case SHA256 = HashAlgorithms::SHA256;
    case SHA384 = HashAlgorithms::SHA384;
    case SHA512_224 = HashAlgorithms::SHA512_224;
    case SHA512_256 = HashAlgorithms::SHA512_256;
    case SHA512 = HashAlgorithms::SHA512;
    case SHA3_224 = HashAlgorithms::SHA3_224;
    case SHA3_256 = HashAlgorithms::SHA3_256;
    case SHA3_384 = HashAlgorithms::SHA3_384;
    case SHA3_512 = HashAlgorithms::SHA3_512;
    case RIPEMD128 = HashAlgorithms::RIPEMD128;
    case RIPEMD160 = HashAlgorithms::RIPEMD160;
    case RIPEMD256 = HashAlgorithms::RIPEMD256;
    case RIPEMD320 = HashAlgorithms::RIPEMD320;
    case WHIRLPOOL = HashAlgorithms::WHIRLPOOL;
    case TIGER128_3 = HashAlgorithms::TIGER128_3;
    case TIGER160_3 = HashAlgorithms::TIGER160_3;
    case TIGER192_3 = HashAlgorithms::TIGER192_3;
    case TIGER128_4 = HashAlgorithms::TIGER128_4;
    case TIGER160_4 = HashAlgorithms::TIGER160_4;
    case TIGER192_4 = HashAlgorithms::TIGER192_4;
    case SNEFRU = HashAlgorithms::SNEFRU;
    case SNEFRU256 = HashAlgorithms::SNEFRU256;
    case GOST = HashAlgorithms::GOST;
    case GOST_CRYPTO = HashAlgorithms::GOST_CRYPTO;
    case ADLER32 = HashAlgorithms::ADLER32;
    case CRC32 = HashAlgorithms::CRC32;
    case CRC32B = HashAlgorithms::CRC32B;
    case CRC32C = HashAlgorithms::CRC32C;
    case FNV132 = HashAlgorithms::FNV132;
    case FNV1A32 = HashAlgorithms::FNV1A32;
    case FNV164 = HashAlgorithms::FNV164;
    case FNV1A64 = HashAlgorithms::FNV1A64;
    case JOAAT = HashAlgorithms::JOAAT;
    case MURMUR3A = HashAlgorithms::MURMUR3A;
    case MURMUR3C = HashAlgorithms::MURMUR3C;
    case MURMUR3F = HashAlgorithms::MURMUR3F;
    case XXH32 = HashAlgorithms::XXH32;
    case XXH64 = HashAlgorithms::XXH64;
    case XXH3 = HashAlgorithms::XXH3;
    case XXH128 = HashAlgorithms::XXH128;
    case HAVAL128_3 = HashAlgorithms::HAVAL128_3;
    case HAVAL160_3 = HashAlgorithms::HAVAL160_3;
    case HAVAL192_3 = HashAlgorithms::HAVAL192_3;
    case HAVAL224_3 = HashAlgorithms::HAVAL224_3;
    case HAVAL256_3 = HashAlgorithms::HAVAL256_3;
    case HAVAL128_4 = HashAlgorithms::HAVAL128_4;
    case HAVAL160_4 = HashAlgorithms::HAVAL160_4;
    case HAVAL192_4 = HashAlgorithms::HAVAL192_4;
    case HAVAL224_4 = HashAlgorithms::HAVAL224_4;
    case HAVAL256_4 = HashAlgorithms::HAVAL256_4;
    case HAVAL128_5 = HashAlgorithms::HAVAL128_5;
    case HAVAL160_5 = HashAlgorithms::HAVAL160_5;
    case HAVAL192_5 = HashAlgorithms::HAVAL192_5;
    case HAVAL224_5 = HashAlgorithms::HAVAL224_5;
    case HAVAL256_5 = HashAlgorithms::HAVAL256_5;
}
