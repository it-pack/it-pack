<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Skeleton gate for Milestone 0.
 *
 * Asserts the package toolchain is wired correctly before domain behavior exists.
 * Replaced by real unit tests in later milestones.
 */
final class SmokeTest extends Unit
{
    public function testPhpRuntimeMeetsMinimum(): void
    {
        $minimum = 80400;

        self::assertGreaterThanOrEqual($minimum, \PHP_VERSION_ID);
    }

    public function testAutoloaderIsOperational(): void
    {
        self::assertTrue(\class_exists(Unit::class));
    }
}
