<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Factory;

use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use PHPUnit\Framework\TestCase;

/**
 * Proves the factory assembles a working runtime with the negotiated adapter.
 *
 * @covers \ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory
 */
final class AsyncRuntimeFactoryTest extends TestCase
{
    public function testCreateReturnsReadyRuntimes(): void
    {
        $runtime = AsyncRuntimeFactory::create();

        self::assertInstanceOf(AsyncRuntime::class, $runtime);
        self::assertInstanceOf(ExecutionEngineInterface::class, $runtime);
    }

    public function testCreateNegotiatesSwooleWhenCoroutinesAvailable(): void
    {
        if (!class_exists('Swoole\\Coroutine') || !class_exists('Swoole\\Coroutine\\Channel')) {
            self::markTestSkipped('Swoole runtime not available.');
        }
        $capability = $runtime->capability();

        // Swoole is installed: negotiate() selects the Swoole branch.
        self::assertTrue($capability->supportsFibers);
        self::assertTrue($capability->supportsCoroutines);
        self::assertTrue($capability->concurrent);
    }

    public function testCreateProducesRunnableRuntime(): void
    {
        $runtime = AsyncRuntimeFactory::create();

        $value = $runtime->run(
            new \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope('test'),
            static fn (): int => 42,
        );

        self::assertSame(42, $value);
    }

    public function testCreateWithSwooleUsesNativeChannel(): void
    {
        $runtime = AsyncRuntimeFactory::create();

        self::assertInstanceOf(TaskChannelInterface::class, $runtime->channel());
    }

    public function testCreateWithSwooleUsesLazyTelemetryBridge(): void
    {
        $runtime = AsyncRuntimeFactory::create();

        self::assertInstanceOf(TelemetryBridgeInterface::class, $runtime->telemetry());
    }
}
