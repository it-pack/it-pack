<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeCapability;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BridgeCapability::class)]
final class BridgeCapabilityTest extends Unit
{
    public function testPropertiesAreSet(): void
    {
        $capability = new BridgeCapability(true, false, true);

        self::assertTrue($capability->trace);
        self::assertFalse($capability->metrics);
        self::assertTrue($capability->logs);
    }

    public function testAny(): void
    {
        self::assertTrue(new BridgeCapability(true, false, false)->any());
        self::assertFalse(new BridgeCapability(false, false, false)->any());
    }
}
