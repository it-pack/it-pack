<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Adapter\OpenTelemetry;

use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Contract for building OpenTelemetry bridge configuration.
 *
 * @see https://opentelemetry.io/
 */
interface BridgeConfigBuilderInterface
{
    public function withTracerProvider(?TracerProviderInterface $tracerProvider): self;

    public function withMeterProvider(?MeterProviderInterface $meterProvider): self;

    public function withLoggerProvider(?LoggerProviderInterface $loggerProvider): self;

    public function withPropagator(TextMapPropagatorInterface $propagator): self;

    public function withSnapshotPayloadPolicy(object $policy): self;

    public function withObservationLogging(bool $enabled): self;

    public function withObservationSpanEvents(bool $enabled): self;

    public function withOccurrenceLogging(bool $enabled): self;

    public function withOccurrenceSpanEvents(bool $enabled): self;

    /**
     * @param list<string> $allowedAttributeKeys
     */
    public function withMetricAttributeAllowList(array $allowedAttributeKeys): self;

    /**
     * @param list<string> $allowedAttributeKeys
     */
    public function withOccurrenceLogAttributeAllowList(array $allowedAttributeKeys): self;

    public function withMaxInstrumentCacheSize(int $maxInstrumentCacheSize): self;

    public function build(): object;
}
