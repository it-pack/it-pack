<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Application;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Application\EngineCapability;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EngineCapability::class)]
final class EngineCapabilityTest extends Unit
{
    public function testPropertiesAreSet(): void
    {
        $capability = new EngineCapability(true, false, true);

        self::assertTrue($capability->supportsFibers);
        self::assertFalse($capability->supportsCoroutines);
        self::assertTrue($capability->concurrent);
    }
}
