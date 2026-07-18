<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\Hasher;

class HasherTest extends Unit
{
    private function hasher(): Hasher
    {
        return NativeHashIt::create();
    }

    public function testHashString(): void
    {
        $hash = $this->hasher()->hash('abc');

        verify($hash->value())->equals(hash('sha256', 'abc'));
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
        verify($hash->length())->equals(64);
    }

    public function testHashWithEnumAlgorithm(): void
    {
        $hash = $this->hasher()->hash('abc', HashAlgorithm::SHA512);

        verify($hash->algorithm())->equals('sha512');
        verify($hash->value())->equals(hash('sha512', 'abc'));
    }

    public function testHashBinary(): void
    {
        $hash = $this->hasher()->hashBinary('abc');

        verify($hash->binary())->true();
        verify($hash->value())->equals(hash('sha256', 'abc', true));
    }

    public function testHashFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'hello world');

        $hash = $this->hasher()->hashFile(
            new NativeFilepathFileinfo(new \SplFileInfo($tmp)),
            'sha256',
        );

        verify($hash->value())->equals(hash('sha256', 'hello world'));

        unlink($tmp);
    }

    public function testHashFileThrowsOnMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hasher()->hashFile(
            new NativeFilepathFileinfo(new \SplFileInfo('/nonexistent/path/xyz')),
        );
    }

    public function testVerifyMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hash('abc');

        verify($hasher->verify('abc', $expected))->true();
    }

    public function testVerifyRejectsWrongData(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hash('abc');

        verify($hasher->verify('abd', $expected))->false();
    }

    public function testVerifyBinaryMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hashBinary('abc');

        verify($hasher->verifyBinary('abc', $expected))->true();
    }
}
