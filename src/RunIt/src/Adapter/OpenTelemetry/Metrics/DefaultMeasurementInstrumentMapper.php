<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Metrics;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;

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
 * @see https://w3id.org/itpack/runit#measurement
 */
final readonly class DefaultMeasurementInstrumentMapper implements MeasurementInstrumentMapperInterface
{
    /**
     * @param list<string> $metricAttributeAllowList measurement attribute keys allowed as dimensions
     */
    public function __construct(
        private ?MeterProviderInterface $meterProvider,
        private InstrumentCache         $cache,
        private array                   $metricAttributeAllowList,
    ) {
    }

    public function record(MeasurementInterface $measurement): void
    {
        if ($this->meterProvider === null) {
            return;
        }

        $meter = $this->meterProvider->getMeter(BridgeConfig::INSTRUMENTATION_SCOPE);
        $instrument = $this->resolveInstrument($meter, $measurement);
        $attributes = $this->filterAttributes($measurement->attributes());

        $this->apply($instrument, $measurement->value(), $attributes);
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
     * @param int|float                                                                                                        $value
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
