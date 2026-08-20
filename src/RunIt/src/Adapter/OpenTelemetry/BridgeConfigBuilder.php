<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Immutable builder for {@see BridgeConfig}.
 *
 * Each mutator returns a new builder instance so a base configuration can be
 * cloned and extended without mutating the original. The builder only validates
 * that the constructed configuration is internally consistent; it never touches
 * OpenTelemetry global state.
 */
final class BridgeConfigBuilder
{
    private ?TracerProviderInterface $tracerProvider = null;

    private ?MeterProviderInterface $meterProvider = null;

    private ?LoggerProviderInterface $loggerProvider = null;

    private ?TextMapPropagatorInterface $propagator = null;

    private SnapshotPayloadPolicy $snapshotPayloadPolicy = SnapshotPayloadPolicy::HashOnly;

    private bool $mapObservationsToLogs = true;

    private bool $mapObservationsToSpanEvents = false;

    private bool $mapOccurrencesToLogs = true;

    private bool $mapOccurrencesToSpanEvents = true;

    /** @var list<string> */
    private array $metricAttributeAllowList = [];

    private int $maxInstrumentCacheSize = 1024;

    public function withTracerProvider(?TracerProviderInterface $tracerProvider): self
    {
        $clone = clone $this;
        $clone->tracerProvider = $tracerProvider;

        return $clone;
    }

    public function withMeterProvider(?MeterProviderInterface $meterProvider): self
    {
        $clone = clone $this;
        $clone->meterProvider = $meterProvider;

        return $clone;
    }

    public function withLoggerProvider(?LoggerProviderInterface $loggerProvider): self
    {
        $clone = clone $this;
        $clone->loggerProvider = $loggerProvider;

        return $clone;
    }

    public function withPropagator(TextMapPropagatorInterface $propagator): self
    {
        $clone = clone $this;
        $clone->propagator = $propagator;

        return $clone;
    }

    public function withSnapshotPayloadPolicy(SnapshotPayloadPolicy $policy): self
    {
        $clone = clone $this;
        $clone->snapshotPayloadPolicy = $policy;

        return $clone;
    }

    public function withObservationLogging(bool $enabled): self
    {
        $clone = clone $this;
        $clone->mapObservationsToLogs = $enabled;

        return $clone;
    }

    public function withObservationSpanEvents(bool $enabled): self
    {
        $clone = clone $this;
        $clone->mapObservationsToSpanEvents = $enabled;

        return $clone;
    }

    public function withOccurrenceLogging(bool $enabled): self
    {
        $clone = clone $this;
        $clone->mapOccurrencesToLogs = $enabled;

        return $clone;
    }

    public function withOccurrenceSpanEvents(bool $enabled): self
    {
        $clone = clone $this;
        $clone->mapOccurrencesToSpanEvents = $enabled;

        return $clone;
    }

    /**
     * @param list<string> $allowedAttributeKeys
     */
    public function withMetricAttributeAllowList(array $allowedAttributeKeys): self
    {
        $clone = clone $this;
        $clone->metricAttributeAllowList = $allowedAttributeKeys;

        return $clone;
    }

    public function withMaxInstrumentCacheSize(int $maxInstrumentCacheSize): self
    {
        $clone = clone $this;
        $clone->maxInstrumentCacheSize = max(1, $maxInstrumentCacheSize);

        return $clone;
    }

    public function build(): BridgeConfig
    {
        return new BridgeConfig(
            $this->tracerProvider,
            $this->meterProvider,
            $this->loggerProvider,
            $this->propagator ?? new MultiTextMapPropagator([
                TraceContextPropagator::getInstance(),
                BaggagePropagator::getInstance(),
            ]),
            $this->snapshotPayloadPolicy,
            $this->mapObservationsToLogs,
            $this->mapObservationsToSpanEvents,
            $this->mapOccurrencesToLogs,
            $this->mapOccurrencesToSpanEvents,
            $this->metricAttributeAllowList,
            $this->maxInstrumentCacheSize,
        );
    }
}
