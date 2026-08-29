<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashInterface;

class HashTest extends Unit
{
    public function testConstructsAndExposesValue(): void
    {
        $hash = new Hash('deadbeef', 'sha256', false);

        verify($hash->value())->equals('deadbeef');
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
        verify($hash->length())->equals(8);
        verify((string) $hash)->equals('deadbeef');
    }

    public function testBinaryFlag(): void
    {
        $hash = new Hash('xxxx', 'sha256', true);

        verify($hash->binary())->true();
    }

    public function testCreateFactory(): void
    {
        $hash = Hash::create('abc', 'md5');

        verify($hash->algorithm())->equals('md5');
        verify($hash->value())->equals('abc');
    }

    public function testEqualsMatchesEqualHashes(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('deadbeef', 'sha256', false);

        verify($a->equals($b))->true();
    }

    public function testEqualsRejectsDifferentValues(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('feedface', 'sha256', false);

        verify($a->equals($b))->false();
    }

    public function testEqualsRejectsDifferentAlgorithm(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('deadbeef', 'md5', false);

        verify($a->equals($b))->false();
    }

    public function testEqualsRejectsDifferentBinaryFlag(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('deadbeef', 'sha256', true);

        verify($a->equals($b))->false();
    }

    public function testToUrnUsesHexRegardlessOfBinaryFlag(): void
    {
        $hash = new Hash('deadbeef', 'sha256', false);

        verify($this->urn($hash))->equals('urn:hashit:sha256:deadbeef');
    }

    public function testToUrnIdenticalForEqualHashes(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('deadbeef', 'sha256', false);

        verify($this->urn($a))->equals($this->urn($b));
    }

    public function testToUrnDiffersByAlgorithm(): void
    {
        $a = new Hash('deadbeef', 'sha256', false);
        $b = new Hash('deadbeef', 'md5', false);

        verify($this->urn($a))->notEquals($this->urn($b));
    }

    public function testToUrnIdenticalForBinaryAndNonBinarySameDigest(): void
    {
        $hex = hash('sha256', 'hello world');
        $raw = hex2bin($hex);

        $nonBinary = new Hash($hex, 'sha256', false);
        $binary = new Hash($raw, 'sha256', true);

        verify($this->urn($nonBinary))->equals($this->urn($binary));
        verify($this->urn($nonBinary))->equals('urn:hashit:sha256:' . $hex);
    }

    private function urn(HashInterface $hash): string
    {
        return $hash->toUrn();
    }
}
