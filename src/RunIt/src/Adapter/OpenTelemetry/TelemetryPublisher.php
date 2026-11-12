<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultObservationLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\DefaultMeasurementInstrumentMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultActivitySpanMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use RuntimeException;
use Throwable;

/**
 * OpenTelemetry bridge publisher.
 *
 * The single entry point that adapts RunIt artifacts to OpenTelemetry signals. It
 * receives every published artifact and dispatches it to the appropriate mapper,
 * honoring the capability of the configured providers. A disabled provider (null)
 * causes the corresponding signal to be skipped without error, so the core remains
 * usable without OpenTelemetry and the bridge degrades gracefully.
 *
 * The publisher never configures global OpenTelemetry providers; every provider is
 * supplied through {@see BridgeConfig}. Build it with {@see self::fromConfig()}.
 * A custom observation mapper without redacted-observation support causes a
 * redacted observation to be reported through the internal health reporter and
 * dropped (fail-open); no publication exception escapes.
 *
 * @see https://opentelemetry.io/
 */
final class TelemetryPublisher implements ArtifactPublisherInterface
{
    public function __construct(
        public readonly BridgeConfig                          $config,
        private readonly ExecutionSpanMapperInterface         $executionMapper,
        private readonly ActivitySpanMapperInterface          $activityMapper,
        private readonly MeasurementInstrumentMapperInterface $measurementMapper,
        private readonly ObservationLogMapperInterface        $observationMapper,
        private readonly OccurrenceLogMapperInterface         $occurrenceMapper,
        private readonly ?InternalHealthReporterInterface $health = null,
        private bool                             $publishingHealthReport = false,
    ) {
    }

    public static function fromConfig(
        BridgeConfig $config,
        ?ExecutionSpanStoreInterface $store = null,
        ?InternalHealthReporterInterface $health = null,
    ): self {
        $store ??= new InMemoryExecutionSpanStore();

        return new self(
            $config,
            new DefaultExecutionSpanMapper($config->tracerProvider, $store, new SpanStatusMap(), new SpanKindMap()),
            new DefaultActivitySpanMapper($config->tracerProvider, $store, new SpanStatusMap(), new SpanKindMap()),
            new DefaultMeasurementInstrumentMapper(
                $config->meterProvider,
                new InstrumentCache($config->maxInstrumentCacheSize),
                $config->metricAttributeAllowList,
            ),
            new DefaultObservationLogMapper(
                $config->mapObservationsToLogs ? $config->loggerProvider : null,
                new SeverityMap(),
                $config->mapObservationsToSpanEvents,
            ),
            new DefaultOccurrenceLogMapper(
                $config->loggerProvider,
                $config->snapshotPayloadPolicy,
                $config->mapOccurrencesToLogs,
                $config->mapOccurrencesToSpanEvents,
                $config->occurrenceLogAttributeAllowList,
            ),
            $health,
        );
    }

    public function capability(): BridgeCapability
    {
        return BridgeCapability::detect($this->config);
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($artifact instanceof ExecutionInterface) {
            $this->executionMapper->map($artifact);

            return;
        }

        if ($artifact instanceof ActivityInterface) {
            $this->activityMapper->map($artifact);

            return;
        }

        if ($artifact instanceof MeasurementInterface) {
            $this->measurementMapper->record($artifact);

            return;
        }

        if ($artifact instanceof ObservationInterface) {
            $this->observationMapper->map($artifact);

            return;
        }

        if ($artifact instanceof OccurrenceInterface) {
            $this->occurrenceMapper->mapOccurrence($artifact);

            return;
        }

        if ($artifact instanceof SnapshotInterface) {
            $this->occurrenceMapper->mapSnapshot($artifact);

            return;
        }

        if ($artifact instanceof RedactedArtifactInterface) {
            $this->publishRedactedArtifact($artifact);

            return;
        }
    }

    private function publishRedactedArtifact(RedactedArtifactInterface $artifact): void
    {
        $normalized = $artifact->normalize();

        if (isset($normalized['source'])) {
            $this->occurrenceMapper->mapRedactedOccurrence($normalized);

            return;
        }

        if (array_key_exists('classification', $normalized)) {
            $this->occurrenceMapper->mapRedactedSnapshot($normalized);

            return;
        }

        if (array_key_exists('result', $normalized)) {
            $this->executionMapper->mapRedactedExecution($normalized);

            return;
        }

        if (array_key_exists('severity', $normalized) || array_key_exists('level', $normalized)) {
            $this->publishRedactedObservation($normalized);

            return;
        }

        if (array_key_exists('unit', $normalized)) {
            // Redacted measurements have no typed redacted mapper on the
            // MeasurementInstrumentMapperInterface contract; they are
            // dropped here rather than misrouted through activity heuristics.

            return;
        }

        if (array_key_exists('execution_id', $normalized) && !array_key_exists('unit', $normalized)) {
            $this->activityMapper->mapRedactedActivity($normalized);
        }
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function publishRedactedObservation(array $normalized): void
    {
        if ($this->observationMapper instanceof RedactedObservationLogMapperInterface) {
            $this->observationMapper->mapRedactedObservation($normalized);

            return;
        }

        // Fail-open: report the unsupported redacted observation through
        // the health channel and drop it instead of throwing, so no
        // publication exception escapes and the business result is preserved.
        // Only a bounded identity projection is sent to the health reporter
        // so no unredacted attribute payload leaks through the health channel.
        // When no health reporter is supplied, the observation is dropped
        // without a health report.
        $id = is_string($normalized['id'] ?? null)
            ? $normalized['id']
            : 'redacted';
        if (strlen($id) > StringIdentifierValidator::MAX_LENGTH) {
            $id = substr($id, 0, StringIdentifierValidator::MAX_LENGTH - 3) . '...';
        }

        if ($this->publishingHealthReport) {
            // Re-entry guard: health reporting must not re-enter the same
            // publication path. Skip the health notice and drop the redacted
            // observation fail-open.
            return;
        }

        try {
            $this->publishingHealthReport = true;
            $this->health?->report(
                new RuntimeException('Observation mapper does not support redacted observations.'),
                new readonly class ($id) implements ExecutionArtifactInterface {
                    public function __construct(
                        private string $id,
                    ) {
                    }

                    public function normalize(): array
                    {
                        return [
                            'id' => $this->id,
                            'type' => 'runit.artifact.redacted',
                        ];
                    }
                },
            );
        } catch (Throwable) {
            // Contain health reporter failures so the drop path stays
            // fail-open: a throwing reporter must not change the business
            // result or let a publication exception escape.
        } finally {
            $this->publishingHealthReport = false;
        }
    }
}
