<?php

declare(strict_types=1);

namespace ItPack\ConfigureItRunit;

use ItPack\ConfigureIt\Contract\Execution\ObservationInterface;
use ItPack\ConfigureIt\Contract\Execution\OccurrenceInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * Bridges ConfigureIt resolution telemetry onto a RunIt execution (M14.7).
 *
 * ConfigureIt emits coarse stage occurrences (ConfigurationLoaded, Merged,
 * Validated, Resolved, SnapshotCreated, Drift, ...) through its own
 * {@see ResolutionReporterInterface}. This reporter implements that contract and
 * forwards each fact to the ambient RunIt execution's recorders, expanding the
 * coarse stages into the finer RunIt activity vocabulary (file-discovery,
 * resource-reading, YAML-loading, document-merge, schema-validation,
 * variable-resolution, graph-building, snapshot-freezing) and deriving the
 * measurements the milestone calls for (files-loaded, duration, memory,
 * graph-node, warnings, validation diagnostics).
 *
 * Neither package imports the other: this reporter is the only meeting point.
 */
final class RunItResolutionReporter implements ResolutionReporterInterface
{
    private const FACT_PREFIX = 'configureit.';
    private const OCCURRENCE_VERSION = '.v1';
    private const SOURCE = 'it-pack/configure-it';

    /** Documented RunIt activities, opened when resolution begins. */
    private const ACTIVITIES = [
        'file_discovery',
        'resource_reading',
        'yaml_loading',
        'document_merge',
        'schema_validation',
        'variable_resolution',
        'graph_building',
        'snapshot_freezing',
    ];

    /** Activities closed when each ConfigureIt stage occurrence is recorded. */
    private const STAGE_ACTIVITIES = [
        'ConfigurationLoaded' => ['file_discovery', 'resource_reading', 'yaml_loading'],
        'ConfigurationMerged' => ['document_merge'],
        'ConfigurationValidated' => ['schema_validation'],
        'ConfigurationResolved' => ['variable_resolution', 'graph_building'],
        'SnapshotCreated' => ['snapshot_freezing'],
    ];

    private float $startedAt;

    /**
     * @var list<OccurrenceInterface>
     */
    private array $occurrences = [];

    /**
     * @var list<ObservationInterface>
     */
    private array $observations = [];

    private int $filesLoaded = 0;
    private int $warnings = 0;
    private int $validationErrors = 0;
    private int $validationWarnings = 0;
    private int $graphNodes = 0;

    public function __construct(
        private ExecutionScopeInterface $scope,
    ) {
        $this->startedAt = microtime(true);

        foreach (self::ACTIVITIES as $activity) {
            $this->activityStarted($activity);
        }
    }

    public function record(OccurrenceInterface $occurrence): void
    {
        $this->occurrences[] = $occurrence;

        $this->occur(
            $this->localType($occurrence->type()),
            $occurrence->payload(),
        );

        $this->closeStageActivities($occurrence->type());
        $this->recordStageMeasurements($occurrence);
    }

    public function observe(ObservationInterface $observation): void
    {
        $this->observations[] = $observation;

        if ($observation->level() === 'warning') {
            $this->warnings++;
        }

        $this->scope->observations()->record(
            $observation->message(),
            $this->severity($observation->level()),
            $this->attributes($observation->context()),
        );
    }

    public function occurrences(): array
    {
        return $this->occurrences;
    }

    public function observations(): array
    {
        return $this->observations;
    }

    /**
     * Maps a ConfigureIt occurrence type to its local RunIt occurrence name.
     */
    private function localType(string $type): string
    {
        return match ($type) {
            'ConfigurationLoaded' => 'configuration.loaded',
            'ConfigurationMerged' => 'configuration.merged',
            'ConfigurationValidated' => 'configuration.validated',
            'ConfigurationResolved' => 'configuration.resolved',
            'SnapshotCreated' => 'configuration.snapshot_created',
            'ConfigurationDrift' => 'configuration.drift',
            'ConfigurationReloaded' => 'configuration.reloaded',
            'ConfigurationChanged' => 'configuration.changed',
            default => 'configuration.' . strtolower($type),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function occur(string $localType, array $payload): void
    {
        $this->scope->occurrences()->record(
            OccurrenceType::fromString(self::FACT_PREFIX . $localType . self::OCCURRENCE_VERSION),
            self::SOURCE,
            $payload === [] ? null : $payload,
        );
    }

    private function activityStarted(string $name): void
    {
        $this->scope->occurrences()->record(
            OccurrenceType::fromString(
                self::FACT_PREFIX . 'activity.' . $name . '.started' . self::OCCURRENCE_VERSION,
            ),
            self::SOURCE,
            ['activity' => $name],
        );
    }

    private function activityFinished(string $name): void
    {
        $this->scope->occurrences()->record(
            OccurrenceType::fromString(
                self::FACT_PREFIX . 'activity.' . $name . '.finished' . self::OCCURRENCE_VERSION,
            ),
            self::SOURCE,
            ['activity' => $name, 'successful' => true],
        );
    }

    /**
     * Closes the activities associated with a completed ConfigureIt stage.
     */
    private function closeStageActivities(string $type): void
    {
        foreach (self::STAGE_ACTIVITIES[$type] ?? [] as $activity) {
            $this->activityFinished($activity);
        }
    }

    /**
     * Derives and records the milestone measurements from a stage occurrence.
     */
    private function recordStageMeasurements(OccurrenceInterface $occurrence): void
    {
        $type = $occurrence->type();
        $payload = $occurrence->payload();

        if ($type === 'ConfigurationLoaded') {
            $this->filesLoaded++;
            $this->scope->measurements()->counter(
                MeasurementName::fromString(self::FACT_PREFIX . 'files_loaded'),
                $this->filesLoaded,
                MeasurementUnit::Count,
            );

            return;
        }

        if ($type === 'ConfigurationValidated') {
            $this->validationErrors = (int) ($payload['errors'] ?? 0);
            $this->validationWarnings = (int) ($payload['warnings'] ?? 0);
            $this->scope->measurements()->counter(
                MeasurementName::fromString(self::FACT_PREFIX . 'validation_errors'),
                $this->validationErrors,
                MeasurementUnit::Count,
            );
            $this->scope->measurements()->counter(
                MeasurementName::fromString(self::FACT_PREFIX . 'validation_warnings'),
                $this->validationWarnings,
                MeasurementUnit::Count,
            );

            return;
        }

        if ($type === 'ConfigurationResolved') {
            $this->graphNodes = (int) ($payload['nodes'] ?? 0);
            $this->scope->measurements()->counter(
                MeasurementName::fromString(self::FACT_PREFIX . 'graph_nodes'),
                $this->graphNodes,
                MeasurementUnit::Count,
            );

            return;
        }

        if ($type === 'SnapshotCreated') {
            $durationMs = (microtime(true) - $this->startedAt) * 1000.0;
            $memoryBytes = memory_get_peak_usage(true);

            $this->scope->measurements()->duration(
                MeasurementName::fromString(self::FACT_PREFIX . 'duration'),
                $durationMs,
                MeasurementUnit::Milliseconds,
            );
            $this->scope->measurements()->size(
                MeasurementName::fromString(self::FACT_PREFIX . 'memory'),
                $memoryBytes,
                MeasurementUnit::Bytes,
            );

            $this->scope->snapshots()->record(
                SnapshotType::fromString(self::FACT_PREFIX . 'configuration.resolved'),
                [
                    'files_loaded' => $this->filesLoaded,
                    'graph_nodes' => $this->graphNodes,
                    'validation_errors' => $this->validationErrors,
                    'validation_warnings' => $this->validationWarnings,
                    'warnings' => $this->warnings,
                    'duration_ms' => round($durationMs, 3),
                    'memory_bytes' => $memoryBytes,
                ],
                SnapshotClassification::Terminal,
            );
        }
    }

    private function severity(string $level): ObservationSeverity
    {
        return match ($level) {
            'info' => ObservationSeverity::Info,
            'warning' => ObservationSeverity::Warning,
            'error' => ObservationSeverity::Error,
            default => ObservationSeverity::Info,
        };
    }

    /**
     * Re-types ConfigureIt's free-form observation context into RunIt's
     * scalar-only attribute shape, dropping non-scalar values.
     *
     * @param array<string, mixed> $context
     */
    private function attributes(array $context): ?AttributeCollection
    {
        $filtered = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $filtered[$key] = $value;
            }
        }

        return $filtered === [] ? null : AttributeCollection::fromArray($filtered);
    }
}
