<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\AlgorithmStatus;
use ItPack\HashIt\HashAlgorithm;

class AlgorithmInfoTest extends Unit
{
    public function testRecommendedAlgorithmReportsOutputBitsAndStatus(): void
    {
        $info = HashAlgorithm::SHA256->info();

        verify($info->outputBits())->equals(256);
        verify($info->status())->equals(AlgorithmStatus::Recommended);
        verify($info->isRecommended())->true();
        verify($info->isBroken())->false();
    }

    public function testBrokenAlgorithmStillHmacSafe(): void
    {
        $info = HashAlgorithm::MD5->info();

        verify($info->status())->equals(AlgorithmStatus::Broken);
        verify($info->isBroken())->true();
        // Collision-broken but HMAC-safe: orthogonal concerns.
        verify(HashAlgorithm::MD5->isHmacSafe())->true();
    }

    public function testRecommendedAlgorithmsExcludesBrokenOnes(): void
    {
        $recommended = HashAlgorithm::recommendedAlgorithms();

        verify(in_array(HashAlgorithm::MD5, $recommended, true))->false();
        verify(in_array(HashAlgorithm::SHA1, $recommended, true))->false();
        verify(in_array(HashAlgorithm::SHA256, $recommended, true))->true();
        verify(in_array(HashAlgorithm::SHA3_512, $recommended, true))->true();
    }

    public function testBrokenAlgorithmsIncludesMd5AndSha1(): void
    {
        $broken = HashAlgorithm::brokenAlgorithms();

        verify(in_array(HashAlgorithm::MD5, $broken, true))->true();
        verify(in_array(HashAlgorithm::SHA1, $broken, true))->true();
        verify(in_array(HashAlgorithm::SHA256, $broken, true))->false();
    }

    public function testMetadataExposesBlockAndOutputBits(): void
    {
        verify(HashAlgorithm::SHA256->info()->blockBits())->equals(512);
        verify(HashAlgorithm::SHA512->info()->blockBits())->equals(1024);
        verify(HashAlgorithm::CRC32->info()->blockBits())->equals(0);
    }

    public function testInfoIsMemoizedAndPure(): void
    {
        verify(HashAlgorithm::SHA256->info())->same(HashAlgorithm::SHA256->info());
    }
}
