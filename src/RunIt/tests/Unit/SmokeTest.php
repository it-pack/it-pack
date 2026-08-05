<?php

declare(strict_types=1);

namespace Tests\Unit;

final class SmokeTest extends \Codeception\Test\Unit
{
    public function testCodeceptionIsConfigured(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/composer.json');
    }
}
