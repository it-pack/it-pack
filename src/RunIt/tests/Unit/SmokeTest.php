<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SmokeTest extends Unit
{
    public function testCodeceptionIsConfigured(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/composer.json');
    }
}
