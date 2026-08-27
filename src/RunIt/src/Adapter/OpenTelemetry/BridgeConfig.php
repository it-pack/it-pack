<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Immutable configuration for the RunIt OpenTelemetry bridge.
 *
 * The bridge never configures global OpenTelemetry providers. Every provider is
 * injected through this value object, which also carries policy knobs that decide
 * how RunIt artifacts are mapped to traces, metrics, and logs. Providers may be
 * null to disable a signal; the bridge degrades gracefully for any null signal.
 *
 * Build instances with {@see BridgeConfigBuilder}. The {@see self::default()}
 * instance assumes no providers are wired and therefore exports nothing until
 * the application supplies providers.
 *
 * @see https://opentelemetry.io/
 */
final readonly class BridgeConfig
{
    public const string INSTRUMENTATION_SCOPE = 'it-pack/run-it';

    /**
     * @param list<string>            $metricAttributeAllowList keys of measurement attributes that
     *                                                        may become metric dimensions. High-cardinality
     *                                                        keys (ids, correlation) are never allowed.
     */
    public function __construct(
        public ?TracerProviderInterface $tracerProvider,
        public ?MeterProviderInterface $meterProvider,
        public ?LoggerProviderInterface $loggerProvider,
        public TextMapPropagatorInterface $propagator,
        public SnapshotPayloadPolicy $snapshotPayloadPolicy,
        public bool $mapObservationsToLogs,
        public bool $mapObservationsToSpanEvents,
        public bool $mapOccurrencesToLogs,
        public bool $mapOccurrencesToSpanEvents,
        public array $metricAttributeAllowList,
        public int $maxInstrumentCacheSize,
        /**
         * @var list<string> extension-attribute keys exported to OTel logs; empty = unrestricted
         */
        public array $occurrenceLogAttributeAllowList = [],
    ) {
    }

    public static function default(): self
    {
        return new BridgeConfigBuilder()->build();
    }

    public static function builder(): BridgeConfigBuilder
    {
        return new BridgeConfigBuilder();
    }
}
