<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Metrics;

use ArrayObject;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedMeasurementInstrumentMapperInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use RuntimeException;
use Throwable;

/**
 * Maps RunIt measurements to OpenTelemetry instruments.
 *
 * The measurement kind selects the instrument type: counters and sizes become
 * counters, gauges become gauges, and durations and ratios become histograms.
 * Units are forwarded as instrument units. Instruments are cached by a stable
 * identity and the cache is bounded. Metric attributes are restricted to an
 * allow list so high-cardinality dimensions (ids, correlation) are never added
 * by default, protecting downstream metric backends.
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
final readonly class DefaultMeasurementInstrumentMapper implements
    MeasurementInstrumentMapperInterface,
    RedactedMeasurementInstrumentMapperInterface
{
    /** @var ArrayObject<string, bool> */
    private ArrayObject $healthState;

    /**
     * @param list<string> $metricAttributeAllowList measurement attribute keys allowed as dimensions
     */
    public function __construct(
        private ?MeterProviderInterface $meterProvider,
        private InstrumentCache         $cache,
        private array                   $metricAttributeAllowList,
        private ?InternalHealthReporterInterface $health = null,
    ) {
        $this->healthState = new ArrayObject(['reporting' => false]);
    }

    public function record(MeasurementInterface $measurement): void
    {
        if (!$this->meterProvider instanceof MeterProviderInterface) {
            return;
        }

        $meter = $this->meterProvider->getMeter(BridgeConfig::INSTRUMENTATION_SCOPE);
        $instrument = $this->resolveInstrument($meter, $measurement);
        $attributes = $this->filterAttributes($measurement->attributes());

        $this->apply($instrument, $measurement->value(), $attributes);
    }

    public function mapRedactedMeasurement(array $normalized): void
    {
        if (!$this->meterProvider instanceof MeterProviderInterface) {
            return;
        }

        $nameValue = $normalized[ArtifactField::NAME] ?? null;
        if (!is_string($nameValue) || $nameValue === '') {
            return;
        }

        try {
            $name = MeasurementName::fromString($nameValue);
        } catch (Throwable) {
            return;
        }

        $unitValue = $normalized[ArtifactField::UNIT] ?? null;
        $kindValue = $normalized[ArtifactField::KIND] ?? null;
        $unit = is_string($unitValue) ? MeasurementUnit::tryFrom($unitValue) : null;
        $kind = is_string($kindValue) ? MeasurementKind::tryFrom($kindValue) : null;
        if ($unit === null || $kind === null) {
            $this->reportInvalidValue();
            return;
        }

        $value = $normalized[ArtifactField::VALUE] ?? null;
        if ((!is_int($value) && !is_float($value))
            || (is_float($value) && !is_finite($value))
            || (($kind === MeasurementKind::Counter || $kind === MeasurementKind::Size) && !is_int($value))) {
            $this->reportInvalidValue();
            return;
        }

        $measurementValue = $value;

        $attributesValue = $normalized[ArtifactField::ATTRIBUTES] ?? null;
        $attributes = [];
        if (is_array($attributesValue)) {
            foreach ($attributesValue as $key => $attrValue) {
                if (is_string($key)) {
                    $attributes[$key] = $attrValue;
                }
            }
        }

        $attributes = $this->filterAttributes($attributes);

        $meter = $this->meterProvider->getMeter(BridgeConfig::INSTRUMENTATION_SCOPE);
        $instrument = $this->resolveInstrumentFromRedacted($meter, $name, $unit, $kind);

        $this->apply($instrument, $measurementValue, $attributes);
    }

    private function reportInvalidValue(): void
    {
        if ($this->healthState['reporting'] === true) {
            return;
        }

        $this->healthState['reporting'] = true;
        try {
            if ($this->health instanceof InternalHealthReporterInterface) {
                $this->health->report(
                    new RuntimeException('Redacted measurement has invalid fields.'),
                    new readonly class () implements ExecutionArtifactInterface {
                        public function normalize(): array
                        {
                            return ['id' => 'redacted', 'type' => 'runit.artifact.redacted'];
                        }
                    },
                );
                $this->healthState['reporting'] = false;
                return;
            }
        } catch (Throwable) {
            // Do not include the reporter exception, which may contain payload data.
        }

        try {
            error_log('[runit] publication health: Redacted measurement has invalid fields.');
        } catch (Throwable) {
            // The last-resort sink must not affect the business outcome.
        } finally {
            $this->healthState['reporting'] = false;
        }
    }

    private function resolveInstrumentFromRedacted(
        MeterInterface $meter,
        MeasurementName $name,
        MeasurementUnit $unit,
        MeasurementKind $kind,
    ): object {
        $key = $name->toString() . '|' . $unit->label() . '|' . $kind->label();

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $instrument = match ($kind) {
            MeasurementKind::Counter, MeasurementKind::Size => $meter->createCounter($name->toString(), $unit->label()),
            MeasurementKind::Gauge => $meter->createGauge($name->toString(), $unit->label()),
            MeasurementKind::Duration, MeasurementKind::Ratio =>
                $meter->createHistogram($name->toString(), $unit->label()),
        };

        $this->cache->put($key, $instrument);

        return $instrument;
    }

    private function resolveInstrument(MeterInterface $meter, MeasurementInterface $measurement): object
    {
        $key = $measurement->name()->toString()
            . '|' . $measurement->unit()->label()
            . '|' . $measurement->kind()->label();

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $instrument = $this->createInstrument($meter, $measurement);
        $this->cache->put($key, $instrument);

        return $instrument;
    }

    private function createInstrument(MeterInterface $meter, MeasurementInterface $measurement): object
    {
        $name = $measurement->name()->toString();
        $unit = $measurement->unit()->label();
        $kind = MeasurementKind::from($measurement->kind()->label());

        return match ($kind) {
            MeasurementKind::Counter, MeasurementKind::Size => $meter->createCounter($name, $unit),
            MeasurementKind::Gauge => $meter->createGauge($name, $unit),
            MeasurementKind::Duration, MeasurementKind::Ratio => $meter->createHistogram($name, $unit),
        };
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<non-empty-string, array<int|string, int|float|string|bool|null>|bool|float|int|string|null>
     */
    private function filterAttributes(array $attributes): array
    {
        if ($this->metricAttributeAllowList === []) {
            return [];
        }

        $allowed = array_flip($this->metricAttributeAllowList);
        $result = array_intersect_key($attributes, $allowed);

        /** @var array<non-empty-string, array<int|string, int|float|string|bool|null>|bool|float|int|string|null> $result */
        return $result;
    }

    /**
     * @param iterable<non-empty-string, array<int|string, int|float|string|bool|null>|bool|float|int|string|null> $attributes
     */
    private function apply(object $instrument, int|float $value, iterable $attributes): void
    {
        if ($instrument instanceof CounterInterface || $instrument instanceof UpDownCounterInterface) {
            $instrument->add($value, $attributes);

            return;
        }

        if ($instrument instanceof GaugeInterface) {
            $instrument->record($value, $attributes);

            return;
        }

        if ($instrument instanceof HistogramInterface) {
            $instrument->record($value, $attributes);
        }
    }
}
