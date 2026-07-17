<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Hash;

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
}
