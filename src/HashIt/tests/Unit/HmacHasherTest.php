<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\NativeHmacHasher;
use ItPack\HashIt\Factory\HashFactory;
use ItPack\HashIt\HashAlgorithm;

class HmacHasherTest extends Unit
{
    private function hasher(): NativeHmacHasher
    {
        return new NativeHmacHasher(new HashFactory());
    }

    public function testHmacMatchesNativeHashHmac(): void
    {
        $hash = $this->hasher()->hmac('message', 'secret', 'sha256');

        verify($hash->value())->equals(hash_hmac('sha256', 'message', 'secret'));
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
    }

    public function testHmacAcceptsEnumAlgorithm(): void
    {
        $hash = $this->hasher()->hmac('message', 'secret', HashAlgorithm::SHA512);

        verify($hash->value())->equals(hash_hmac('sha512', 'message', 'secret'));
    }

    public function testHmacRejectsNonHmacSafeAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hmac('message', 'secret', HashAlgorithm::CRC32);
    }

    public function testHmacRejectsUnknownAlgorithmString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hmac('message', 'secret', 'bogus');
    }

    public function testCompositionRootExposesHmacHasher(): void
    {
        $hmac = NativeHashIt::hmacHasher();

        verify($hmac->hmac('message', 'secret')->value())->equals(hash_hmac('sha256', 'message', 'secret'));
    }
}
