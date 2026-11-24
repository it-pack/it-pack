<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BridgeConfigBuilder::class)]
final class BridgeConfigBuilderTest extends Unit
{
    public function testBuildReturnsDefaults(): void
    {
        $config = new BridgeConfigBuilder()->build();

        self::assertInstanceOf(BridgeConfig::class, $config);
        self::assertNull($config->tracerProvider);
        self::assertNull($config->meterProvider);
        self::assertNull($config->loggerProvider);
        self::assertSame(SnapshotPayloadPolicy::HashOnly, $config->snapshotPayloadPolicy);
        self::assertTrue($config->mapObservationsToLogs);
        self::assertFalse($config->mapObservationsToSpanEvents);
        self::assertTrue($config->mapOccurrencesToLogs);
        self::assertTrue($config->mapOccurrencesToSpanEvents);
        self::assertSame([], $config->metricAttributeAllowList);
        self::assertSame(1024, $config->maxInstrumentCacheSize);
        self::assertSame([], $config->occurrenceLogAttributeAllowList);
    }

    public function testWithTracerProviderReturnsNewBuilder(): void
    {
        $tracer = $this->createMock(TracerProviderInterface::class);
        $builder = new BridgeConfigBuilder()->withTracerProvider($tracer);

        self::assertNotSame(new BridgeConfigBuilder()->build(), $builder->build());
        self::assertSame($tracer, $builder->build()->tracerProvider);
    }

    public function testWithMeterProvider(): void
    {
        $meter = $this->createMock(MeterProviderInterface::class);
        $builder = new BridgeConfigBuilder()->withMeterProvider($meter);

        self::assertSame($meter, $builder->build()->meterProvider);
    }

    public function testWithLoggerProvider(): void
    {
        $logger = $this->createMock(LoggerProviderInterface::class);
        $builder = new BridgeConfigBuilder()->withLoggerProvider($logger);

        self::assertSame($logger, $builder->build()->loggerProvider);
    }

    public function testWithPropagator(): void
    {
        $builder = new BridgeConfigBuilder();
        $built = $builder->build();

        self::assertInstanceOf(TextMapPropagatorInterface::class, $built->propagator);
    }

    public function testWithSnapshotPayloadPolicy(): void
    {
        $builder = new BridgeConfigBuilder()->withSnapshotPayloadPolicy(SnapshotPayloadPolicy::Full);

        self::assertSame(SnapshotPayloadPolicy::Full, $builder->build()->snapshotPayloadPolicy);
    }

    public function testWithObservationLogging(): void
    {
        $builder = new BridgeConfigBuilder()->withObservationLogging(false);

        self::assertFalse($builder->build()->mapObservationsToLogs);
    }

    public function testWithObservationSpanEvents(): void
    {
        $builder = new BridgeConfigBuilder()->withObservationSpanEvents(true);

        self::assertTrue($builder->build()->mapObservationsToSpanEvents);
    }

    public function testWithOccurrenceLogging(): void
    {
        $builder = new BridgeConfigBuilder()->withOccurrenceLogging(false);

        self::assertFalse($builder->build()->mapOccurrencesToLogs);
    }

    public function testWithOccurrenceSpanEvents(): void
    {
        $builder = new BridgeConfigBuilder()->withOccurrenceSpanEvents(false);

        self::assertFalse($builder->build()->mapOccurrencesToSpanEvents);
    }

    public function testWithMetricAttributeAllowList(): void
    {
        $builder = new BridgeConfigBuilder()->withMetricAttributeAllowList(['key1', 'key2']);

        self::assertSame(['key1', 'key2'], $builder->build()->metricAttributeAllowList);
    }

    public function testWithOccurrenceLogAttributeAllowList(): void
    {
        $builder = new BridgeConfigBuilder()->withOccurrenceLogAttributeAllowList(['key1']);

        self::assertSame(['key1'], $builder->build()->occurrenceLogAttributeAllowList);
    }

    public function testWithMaxInstrumentCacheSizeClampsToOne(): void
    {
        $builder = new BridgeConfigBuilder()->withMaxInstrumentCacheSize(0);

        self::assertSame(1, $builder->build()->maxInstrumentCacheSize);
    }

    public function testBuilderIsImmutable(): void
    {
        $builder = new BridgeConfigBuilder();
        $clone = $builder->withTracerProvider($this->createMock(TracerProviderInterface::class));

        self::assertNull($builder->build()->tracerProvider);
        self::assertNotNull($clone->build()->tracerProvider);
    }
}
