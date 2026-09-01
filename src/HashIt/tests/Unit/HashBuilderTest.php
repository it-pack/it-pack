<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeContextFactory;
use ItPack\HashIt\Factory\HashFactory;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashBuilder;

class HashBuilderTest extends Unit
{
    private function factory(): NativeContextFactory
    {
        return new NativeContextFactory();
    }

    private function hashFactory(): HashFactory
    {
        return new HashFactory();
    }

    public function testDigestMatchesSingleHash(): void
    {
        $hash = HashBuilder::start($this->factory(), $this->hashFactory(), 'sha256')
            ->append('hello ')
            ->append('world')
            ->digest()
        ;

        verify($hash->value())->equals(hash('sha256', 'hello world'));
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
    }

    public function testBuilderIsImmutable(): void
    {
        $base = HashBuilder::start($this->factory(), $this->hashFactory(), 'sha256');
        $extended = $base->append('data');

        verify($base->digest()->value())->equals(hash('sha256', ''));
        verify($extended->digest()->value())->equals(hash('sha256', 'data'));
    }

    public function testCopySharesStateAcrossBranches(): void
    {
        $base = HashBuilder::start($this->factory(), $this->hashFactory(), 'sha256')->append('abc');
        $branchA = $base->append('1');
        $branchB = $base->append('2');

        verify($branchA->digest()->value())->equals(hash('sha256', 'abc1'));
        verify($branchB->digest()->value())->equals(hash('sha256', 'abc2'));
    }

    public function testBinaryDigest(): void
    {
        $hash = HashBuilder::start($this->factory(), $this->hashFactory(), 'sha256', true)
            ->append('abc')
            ->digest()
        ;

        verify($hash->binary())->true();
        verify($hash->value())->equals(hash('sha256', 'abc', true));
    }

    public function testAcceptsEnumAlgorithm(): void
    {
        $hash = HashBuilder::start($this->factory(), $this->hashFactory(), HashAlgorithm::SHA512)
            ->append('abc')
            ->digest()
        ;

        verify($hash->algorithm())->equals('sha512');
        verify($hash->value())->equals(hash('sha512', 'abc'));
    }
}
