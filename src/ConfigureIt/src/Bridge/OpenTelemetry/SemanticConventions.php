<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Bridge\OpenTelemetry;

/**
 * OpenTelemetry-aligned semantic conventions for ConfigureIt telemetry.
 *
 * These names follow the OpenTelemetry specification (span names, metric
 * instrument names, attribute keys) so that a backend (Jaeger, Tempo,
 * Prometheus, OTLP collector) renders ConfigureIt data natively. The Core
 * never references these; they live behind the {@see TelemetrySinkInterface}
 * port and are emitted by the {@see OpenTelemetryReporter} decorator built on
 * the Execution reporter spine (docs/EXECUTION.md).
 *
 * @see https://opentelemetry.io/docs/specs/semconv/
 */
final class SemanticConventions
{
    /**
     * Root trace span for a single resolution run.
     */
    public const string SPAN_RESOLVE = 'config.resolve';

    /**
     * Child spans emitted per Execution Occurrence.
     */
    public const string SPAN_LOAD = 'config.load';
    public const string SPAN_MERGE = 'config.merge';
    public const string SPAN_VALIDATE = 'config.validate';
    public const string SPAN_RESOLVE_STAGES = 'config.resolve.stages';
    public const string SPAN_SNAPSHOT = 'config.snapshot';
    public const string SPAN_DRIFT = 'config.drift';

    /**
     * Configuration lifecycle events (CloudEvents-style) emitted on the trace.
     */
    public const string EVENT_LOADED = 'config.loaded';
    public const string EVENT_MERGED = 'config.merged';
    public const string EVENT_VALIDATED = 'config.validated';
    public const string EVENT_RESOLVED = 'config.resolved';
    public const string EVENT_SNAPSHOT_CREATED = 'config.snapshot.created';
    public const string EVENT_DRIFT = 'config.drift';
    public const string EVENT_OBSERVATION = 'config.observation';

    /**
     * Metric instrument names.
     */
    public const string METRIC_RESOLUTION_DURATION = 'config.resolution.duration';
    public const string METRIC_OCCURRENCES = 'config.occurrences';
    public const string METRIC_NODES = 'config.nodes';
    public const string METRIC_OVERRIDES = 'config.overrides';
    public const string METRIC_UNRESOLVED = 'config.unresolved';

    /**
     * Attribute keys (OpenTelemetry semantic where applicable).
     */
    public const string ATTR_SOURCE_KIND = 'config.source.kind';
    public const string ATTR_DOCUMENT_PRIORITY = 'config.document.priority';
    public const string ATTR_STAGE_COUNT = 'config.stage.count';
    public const string ATTR_VALID = 'config.valid';
    public const string ATTR_EVENT = 'config.event';
    public const string ATTR_LEVEL = 'config.observation.level';
    public const string ATTR_ENVIRONMENT = 'deployment.environment';
    public const string ATTR_SERVICE = 'service.name';

    /**
     * Maps an Execution Occurrence type to its OTel span name.
     */
    public static function occurrenceSpan(string $type): ?string
    {
        return match ($type) {
            'ConfigurationLoaded' => self::SPAN_LOAD,
            'ConfigurationMerged' => self::SPAN_MERGE,
            'ConfigurationValidated' => self::SPAN_VALIDATE,
            'ConfigurationResolved' => self::SPAN_RESOLVE_STAGES,
            'SnapshotCreated' => self::SPAN_SNAPSHOT,
            'ConfigurationDrift' => self::SPAN_DRIFT,
            default => null,
        };
    }

    /**
     * Maps an Execution Occurrence type to its OTel lifecycle event name.
     */
    public static function occurrenceEvent(string $type): ?string
    {
        return match ($type) {
            'ConfigurationLoaded' => self::EVENT_LOADED,
            'ConfigurationMerged' => self::EVENT_MERGED,
            'ConfigurationValidated' => self::EVENT_VALIDATED,
            'ConfigurationResolved' => self::EVENT_RESOLVED,
            'SnapshotCreated' => self::EVENT_SNAPSHOT_CREATED,
            'ConfigurationDrift' => self::EVENT_DRIFT,
            default => null,
        };
    }

    /**
     * True for the Occurrence type that closes the resolution trace.
     */
    public static function closesTrace(string $type): bool
    {
        return $type === 'SnapshotCreated';
    }
}
