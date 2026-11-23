<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeCapability;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;

final class BridgeConfigTest extends Unit
{
    public function testDefaultHasNoProviders(): void
    {
        $config = BridgeConfig::default();

        self::assertNull($config->tracerProvider);
        self::assertNull($config->meterProvider);
        self::assertNull($config->loggerProvider);

        $capability = BridgeCapability::detect($config);
        self::assertFalse($capability->trace);
        self::assertFalse($capability->metrics);
        self::assertFalse($capability->logs);
        self::assertFalse($capability->any());
    }

    public function testBuilderWiresProvidersAndPolicy(): void
    {
        $kit = new OtelTestKit();
        $config = new BridgeConfigBuilder()
            ->withTracerProvider($kit->tracerProvider)
            ->withMeterProvider($kit->meterProvider)
            ->withLoggerProvider($kit->loggerProvider)
            ->withSnapshotPayloadPolicy(SnapshotPayloadPolicy::Full)
            ->withMetricAttributeAllowList(['region'])
            ->withObservationLogging(enabled: false)
            ->build()
        ;

        self::assertSame($kit->tracerProvider, $config->tracerProvider);
        self::assertSame(SnapshotPayloadPolicy::Full, $config->snapshotPayloadPolicy);
        self::assertSame(['region'], $config->metricAttributeAllowList);
        self::assertFalse($config->mapObservationsToLogs);

        $capability = BridgeCapability::detect($config);
        self::assertTrue($capability->trace);
        self::assertTrue($capability->metrics);
        self::assertTrue($capability->logs);
        self::assertTrue($capability->any());
    }

    public function testDefaultPropagatorIsComposite(): void
    {
        $config = BridgeConfig::default();

        self::assertContains('traceparent', $config->propagator->fields());
        self::assertContains('baggage', $config->propagator->fields());
    }

    public function testBuilderIsImmutable(): void
    {
        $base = new BridgeConfigBuilder();
        $withTracer = $base->withTracerProvider(new OtelTestKit()->tracerProvider);

        self::assertNull($base->build()->tracerProvider);
        self::assertNotNull($withTracer->build()->tracerProvider);
    }

    public function testStaticBuilderEntryPoint(): void
    {
        $config = BridgeConfig::builder()->build();

        self::assertInstanceOf(BridgeConfig::class, $config);
        self::assertNull($config->tracerProvider);
    }

    public function testBuilderExercisesPropagatorAndSpanEventAndCacheOptions(): void
    {
        $propagator = BridgeConfig::default()->propagator;
        $config = new BridgeConfigBuilder()
            ->withPropagator($propagator)
            ->withObservationSpanEvents(enabled: true)
            ->withMaxInstrumentCacheSize(256)
            ->build()
        ;

        self::assertSame($propagator, $config->propagator);
        self::assertTrue($config->mapObservationsToSpanEvents);
        self::assertSame(256, $config->maxInstrumentCacheSize);
    }

    public function testOccurrenceLogAttributeAllowListFlowsThroughTheBuilder(): void
    {
        $config = new BridgeConfigBuilder()
            ->withOccurrenceLogAttributeAllowList(['tenantflag'])
            ->withOccurrenceSpanEvents(false)
            ->build()
        ;

        self::assertSame(['tenantflag'], $config->occurrenceLogAttributeAllowList);
        self::assertSame([], BridgeConfig::default()->occurrenceLogAttributeAllowList, 'default stays unrestricted');
    }

    public function testOccurrenceLoggingFlowsThroughTheBuilder(): void
    {
        $default = BridgeConfig::default();
        self::assertTrue($default->mapOccurrencesToLogs);

        $config = new BridgeConfigBuilder()
            ->withOccurrenceLogging(false)
            ->build()
        ;

        self::assertFalse($config->mapOccurrencesToLogs);

        $original = new BridgeConfigBuilder();
        $modified = $original->withOccurrenceLogging(false);
        self::assertFalse($modified->build()->mapOccurrencesToLogs);
        self::assertTrue($original->build()->mapOccurrencesToLogs);
    }

    public function testOccurrenceSpanEventsFlowsThroughTheBuilder(): void
    {
        $default = BridgeConfig::default();
        self::assertTrue($default->mapOccurrencesToSpanEvents);

        $config = new BridgeConfigBuilder()
            ->withOccurrenceSpanEvents(false)
            ->build()
        ;

        self::assertFalse($config->mapOccurrencesToSpanEvents);

        $original = new BridgeConfigBuilder();
        $modified = $original->withOccurrenceSpanEvents(false);
        self::assertFalse($modified->build()->mapOccurrencesToSpanEvents);
        self::assertTrue($original->build()->mapOccurrencesToSpanEvents);
    }
}
