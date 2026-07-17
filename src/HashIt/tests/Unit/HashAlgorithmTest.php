<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\HashAlgorithm;

class HashAlgorithmTest extends Unit
{
    public function testDefaultIsSha256(): void
    {
        verify(HashAlgorithm::default())->equals(HashAlgorithm::SHA256);
    }

    public function testHmacSafeDetection(): void
    {
        verify(HashAlgorithm::SHA256->isHmacSafe())->true();
        verify(HashAlgorithm::MD5->isHmacSafe())->true();
        verify(HashAlgorithm::CRC32->isHmacSafe())->false();
        verify(HashAlgorithm::XXH128->isHmacSafe())->false();
    }

    public function testHmacSafeAlgorithmsDerivedFromAttribute(): void
    {
        $safe = HashAlgorithm::hmacSafeAlgorithms();

        verify(in_array(HashAlgorithm::SHA256, $safe, true))->true();
        verify(in_array(HashAlgorithm::MD5, $safe, true))->true();
        verify(in_array(HashAlgorithm::CRC32, $safe, true))->false();

        foreach ($safe as $case) {
            verify($case->isHmacSafe())->true();
        }
    }

    public function testEveryCaseHasAValue(): void
    {
        foreach (HashAlgorithm::cases() as $case) {
            verify($case->value)->notEmpty();
        }
    }
}
