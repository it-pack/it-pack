<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedMeasurementInstrumentMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultObservationLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\DefaultMeasurementInstrumentMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultActivitySpanMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
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
    private bool $writingHealthFallback = false;

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
                $health,
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
        if ($artifact instanceof RedactedArtifactInterface) {
            $this->publishRedactedArtifact($artifact);

            return;
        }

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
            $this->publishObservation($artifact);

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

        $this->publishUnclassified($artifact);
    }

    private function publishObservation(ObservationInterface $artifact): void
    {
        if ($artifact instanceof RedactedArtifactInterface) {
            $this->publishRedactedArtifact($artifact);

            return;
        }

        $this->observationMapper->map($artifact);
    }

    private function publishUnclassified(ExecutionArtifactInterface $artifact): void
    {
        if ($artifact instanceof RedactedArtifactInterface) {
            $this->publishRedactedArtifact($artifact);

            return;
        }

        if ($artifact instanceof TypedExecutionArtifactInterface || method_exists($artifact, 'artifactType')) {
            $type = $artifact->artifactType();
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            $this->dispatchRedactedArtifact(
                is_string($type) ? $type : '',
                $artifact->normalize()
            );
        }
    }

    private function publishRedactedArtifact(RedactedArtifactInterface $artifact): void
    {
        $this->dispatchRedactedArtifact($artifact->artifactType(), $artifact->normalize());
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function dispatchRedactedArtifact(string $artifactType, array $normalized): void
    {
        if ($artifactType === ArtifactType::OBSERVATION) {
            $this->publishRedactedObservation($normalized);

            return;
        }

        if ($artifactType === ArtifactType::EXECUTION) {
            $this->executionMapper->mapRedactedExecution($normalized);

            return;
        }

        if ($artifactType === ArtifactType::ACTIVITY) {
            $this->activityMapper->mapRedactedActivity($normalized);

            return;
        }

        if ($artifactType === ArtifactType::MEASUREMENT) {
            $this->dispatchRedactedMeasurement($normalized);

            return;
        }

        if ($artifactType === ArtifactType::OCCURRENCE) {
            $this->occurrenceMapper->mapRedactedOccurrence($normalized);

            return;
        }

        if ($artifactType === ArtifactType::SNAPSHOT) {
            $this->occurrenceMapper->mapRedactedSnapshot($normalized);

            return;
        }

        $this->reportUnknownRedactedArtifact();
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function dispatchRedactedMeasurement(array $normalized): void
    {
        if ($this->measurementMapper instanceof RedactedMeasurementInstrumentMapperInterface) {
            $this->measurementMapper->mapRedactedMeasurement($normalized);

            return;
        }

        $this->reportRedactedDrop('Measurement mapper does not support redacted measurements.');
    }

    private function reportUnknownRedactedArtifact(): void
    {
        $this->reportRedactedDrop('Redacted measurement or unknown artifact kind is not delivered.');
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

        // Fail-open: report the unsupported redacted observation through the
        // health channel and drop it instead of throwing.
        $this->reportRedactedDrop('Observation mapper does not support redacted observations.');
    }

    /**
     * Reports a suppressed redacted artifact through the health channel and drops it.
     *
     * Only a constant, payload-free notice is sent to the health reporter.
     * Health reporter failures and re-entry use a guarded, payload-free fallback.
     */
    private function reportRedactedDrop(string $reason): void
    {
        if ($this->publishingHealthReport) {
            $this->writeHealthFallback();
            return;
        }

        try {
            $this->publishingHealthReport = true;
            if (!$this->health instanceof InternalHealthReporterInterface) {
                /** @noinspection ForgottenDebugOutputInspection */
                error_log('[runit] publication health: ' . $reason);

                return;
            }

            $this->health->report(
                new RuntimeException($reason),
                new readonly class () implements ExecutionArtifactInterface {
                    public function normalize(): array
                    {
                        return [
                            ArtifactField::ID       => AttributeValues::REDACTED_ID,
                            ArtifactField::TYPE     => AttributeValues::REDACTED_TYPE,
                        ];
                    }
                },
            );
        } catch (Throwable) {
            $this->writeHealthFallback();
        } finally {
            $this->publishingHealthReport = false;
        }
    }

    private function writeHealthFallback(): void
    {
        if ($this->writingHealthFallback) {
            return;
        }

        $this->writingHealthFallback = true;
        try {
            /** @noinspection ForgottenDebugOutputInspection */
            error_log('[runit] publication health: unable to report a redacted artifact drop.');
        } catch (Throwable) {
            // Last-resort diagnostics must not affect publication or business outcomes.
        } finally {
            $this->writingHealthFallback = false;
        }
    }
}
