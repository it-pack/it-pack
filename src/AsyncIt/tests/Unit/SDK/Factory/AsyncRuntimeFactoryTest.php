<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Factory;

use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
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

        $runtime = AsyncRuntimeFactory::create();

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
        if (!class_exists('Swoole\\Coroutine\\Channel')) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $runtime = AsyncRuntimeFactory::create();

        self::assertInstanceOf(\ItPack\AsyncIt\Adapter\Swoole\SwooleChannel::class, $runtime->channel());
    }

    public function testCreateWithSwooleUsesLazyTelemetryBridge(): void
    {
        // No OTEL exporter endpoint: the factory must fall back to the lazy bridge.
        putenv('OTEL_EXPORTER_OTLP_ENDPOINT');

        $runtime = AsyncRuntimeFactory::create();

        self::assertInstanceOf(LazyTelemetryBridge::class, $runtime->telemetry());
    }
}
