<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;

/**
 * Skeleton gate for Milestone 0 (functional suite).
 * Replaced by real functional tests in later milestones.
 */
final class SmokeTest extends Unit
{
    public function testRuntimeIsSupported(): void
    {
        $minimum = 80400;

        self::assertGreaterThanOrEqual($minimum, \PHP_VERSION_ID);
    }

    public function testQualityToolingIsPresent(): void
    {
        $vendorBin = \dirname(__DIR__, 2) . '/vendor/bin';

        self::assertFileExists($vendorBin . '/phpstan');
        self::assertFileExists($vendorBin . '/deptrac');
    }
}
