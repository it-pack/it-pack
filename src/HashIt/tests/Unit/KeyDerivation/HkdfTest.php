<?php

declare(strict_types=1);

namespace Tests\Unit\KeyDerivation;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\KeyDerivation\Hkdf;
use ItPack\HashIt\KeyDerivationInterface;

class HkdfTest extends Unit
{
    private function kdf(): KeyDerivationInterface
    {
        return NativeHashIt::kdf();
    }

    public function testCompositionRootExposesHkdf(): void
    {
        self::assertInstanceOf(Hkdf::class, NativeHashIt::kdf());
        self::assertInstanceOf(KeyDerivationInterface::class, NativeHashIt::kdf());
    }

    /**
     * RFC 5869 Appendix A.1 — basic test case with SHA-256.
     */
    public function testRfc5869TestCase1(): void
    {
        $ikm = hex2bin('0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b');
        $salt = hex2bin('000102030405060708090a0b0c');
        $info = hex2bin('f0f1f2f3f4f5f6f7f8f9');

        $okm = $this->kdf()->derive($ikm, $salt, 42, $info, HashAlgorithm::SHA256);

        self::assertSame(42, strlen($okm));
        self::assertSame(
            hex2bin('3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865'),
            $okm,
        );
    }

    /**
     * Omitting the salt (or passing an empty one) must produce the RFC 5869
     * default: a string of HashLen zero octets as the HMAC key.
     */
    public function testOmittedOrEmptySaltDefaultsToHashLenZeros(): void
    {
        $ikm = hex2bin('0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b');

        $omitted = $this->kdf()->derive($ikm, null, 42, '', HashAlgorithm::SHA256);
        $explicitDefault = $this->kdf()->derive($ikm, str_repeat("\0", 32), 42, '', HashAlgorithm::SHA256);
        $empty = $this->kdf()->derive($ikm, '', 42, '', HashAlgorithm::SHA256);

        self::assertSame($explicitDefault, $omitted);
        self::assertSame($explicitDefault, $empty);
        // And it must be deterministic and 42 bytes long.
        self::assertSame(42, strlen($omitted));
    }

    public function testExtractMatchesNativeHmac(): void
    {
        $ikm = hex2bin('0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b');
        $salt = hex2bin('000102030405060708090a0b0c');

        $prk = NativeHashIt::keyedHasher()->hmacBinary($ikm, $salt, HashAlgorithm::SHA256)->value();

        self::assertSame(hex2bin('077709362c2e32df0ddc3f0dc47bba6390b6c73bb50f9c3122ec844ad7c2b3e5'), $prk);
    }

    public function testLongerOutputSpansMultipleBlocks(): void
    {
        $ikm = hex2bin('0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b');
        $salt = hex2bin('000102030405060708090a0b0c');
        $info = hex2bin('f0f1f2f3f4f5f6f7f8f9');

        $okm = $this->kdf()->derive($ikm, $salt, 64, $info, HashAlgorithm::SHA256);

        self::assertSame(64, strlen($okm));
        // First 42 bytes must equal the A.1 vector, proving the expand loop chains correctly.
        self::assertSame(
            hex2bin('3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865'),
            substr($okm, 0, 42),
        );
    }

    public function testMissingLengthExceedsRfcLimitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->kdf()->derive('ikm', 'salt', 255 * 32 + 1, '', HashAlgorithm::SHA256);
    }

    public function testNonPositiveLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->kdf()->derive('ikm', 'salt', 0, '', HashAlgorithm::SHA256);
    }
}
