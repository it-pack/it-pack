<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\SDK\AsyncRuntime
 */
final class AsyncRuntimeAccessorTest extends TestCase
{
    public function testChannelReturnsTaskChannel(): void
    {
        $runtime = $this->createRuntime();

        self::assertInstanceOf(
            TaskChannelInterface::class,
            $runtime->channel(),
        );
    }

    public function testTelemetryReturnsLazyBridge(): void
    {
        $runtime = $this->createRuntime();

        self::assertInstanceOf(TelemetryBridgeInterface::class, $runtime->telemetry());
        self::assertInstanceOf(LazyTelemetryBridge::class, $runtime->telemetry());
    }

    public function testCapabilityReturnsEngineCapability(): void
    {
        $runtime = $this->createRuntime();

        $capability = $runtime->capability();

        self::assertInstanceOf(EngineCapability::class, $capability);
    }

    private function createRuntime(): AsyncRuntime
    {
        return AsyncRuntime::discover();
    }
}
