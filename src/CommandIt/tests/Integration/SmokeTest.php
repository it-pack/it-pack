<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;

/**
 * Skeleton gate for Milestone 0 (integration suite).
 * Replaced by real integration tests (ports, adapters) in later milestones.
 */
final class SmokeTest extends Unit
{
    public function testActorWasGenerated(): void
    {
        self::assertTrue(\class_exists(\Tests\Support\IntegrationTester::class));
    }

    public function testRuntimeIsSupported(): void
    {
        $minimum = 80400;

        self::assertGreaterThanOrEqual($minimum, \PHP_VERSION_ID);
    }
}
