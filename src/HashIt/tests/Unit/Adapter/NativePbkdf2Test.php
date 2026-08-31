<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\NativePbkdf2;
use ItPack\HashIt\Contract\Pbkdf2Interface;
use ItPack\HashIt\HashAlgorithm;

class NativePbkdf2Test extends Unit
{
    private function pbkdf2(): Pbkdf2Interface
    {
        return NativeHashIt::pbkdf2();
    }

    public function testCompositionRootExposesPbkdf2(): void
    {
        self::assertInstanceOf(NativePbkdf2::class, NativeHashIt::pbkdf2());
        self::assertInstanceOf(Pbkdf2Interface::class, NativeHashIt::pbkdf2());
    }

    public function testParityWithNativeHashPbkdf2(): void
    {
        $password = 'correct horse battery staple';
        $salt = 'a-static-salt';
        $iterations = 1500;
        $length = 48;

        $derived = $this->pbkdf2()->derive($password, $salt, $iterations, $length, HashAlgorithm::SHA256);

        self::assertSame($length, strlen($derived));
        self::assertSame(
            hash_pbkdf2('sha256', $password, $salt, $iterations, $length, true),
            $derived,
        );
    }

    public function testDeterministicForFixedInputs(): void
    {
        $first = $this->pbkdf2()->derive('pw', 'salt', 1000, 32, HashAlgorithm::SHA512);
        $second = $this->pbkdf2()->derive('pw', 'salt', 1000, 32, HashAlgorithm::SHA512);

        self::assertSame($first, $second);
    }

    public function testDifferentSaltChangesOutput(): void
    {
        $a = $this->pbkdf2()->derive('pw', 'salt-a', 1000, 32, HashAlgorithm::SHA256);
        $b = $this->pbkdf2()->derive('pw', 'salt-b', 1000, 32, HashAlgorithm::SHA256);

        self::assertNotSame($a, $b);
    }

    public function testNonPositiveIterationsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pbkdf2()->derive('pw', 'salt', 0, 32, HashAlgorithm::SHA256);
    }

    public function testNonPositiveLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pbkdf2()->derive('pw', 'salt', 1000, 0, HashAlgorithm::SHA256);
    }
}
