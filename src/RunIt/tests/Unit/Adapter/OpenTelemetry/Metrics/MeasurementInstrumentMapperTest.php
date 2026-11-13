<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Metrics;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\DefaultMeasurementInstrumentMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use OpenTelemetry\SDK\Metrics\Data\Gauge;
use OpenTelemetry\SDK\Metrics\Data\Histogram;
use OpenTelemetry\SDK\Metrics\Data\Metric;
use OpenTelemetry\SDK\Metrics\Data\Sum;
use RuntimeException;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;
use Throwable;

/**
 * M9.5 — Measurements to metrics.
 */
final class MeasurementInstrumentMapperTest extends Unit
{
    private OtelTestKit $kit;

    protected function _before(): void
    {
        $this->kit = new OtelTestKit();
    }

    /**
     * @return array<string, mixed>
     */
    private function firstAttributes(Metric $metric): array
    {
        $data = $metric->data;
        if ($data instanceof Sum || $data instanceof Gauge || $data instanceof Histogram) {
            foreach ($data->dataPoints as $point) {
                /** @var array<string, mixed> $attributes */
                $attributes = $point->attributes->toArray();

                return $attributes;
            }
        }

        return [];
    }

    public function testCounterBecomesSumWithValue(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, new InstrumentCache(64), []);

        $mapper->record(RunItArtifactFactory::counter('exec-1', 'items', 5));

        $metric = $this->kit->metric('items');

        self::assertNotNull($metric);
        $data = $metric->data;
        self::assertInstanceOf(Sum::class, $data);
        $point = null;
        foreach ($data->dataPoints as $candidate) {
            $point = $candidate;
            break;
        }

        self::assertNotNull($point);
        self::assertSame(5, $point->value);
    }

    public function testGaugeBecomesGaugeInstrument(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, new InstrumentCache(64), []);
        $gauge = Measurement::gauge(
            MeasurementName::fromString('cpu'),
            ExecutionId::fromString('exec-1'),
            0.42,
            instant: Instant::of(1_500),
            unit: MeasurementUnit::Percent
        );

        $mapper->record($gauge);
        $metric = $this->kit->metric('cpu');

        self::assertNotNull($metric);
        $data = $metric->data;
        self::assertInstanceOf(Gauge::class, $data);
        $point = null;
        foreach ($data->dataPoints as $candidate) {
            $point = $candidate;
            break;
        }

        self::assertNotNull($point);
        self::assertSame(0.42, $point->value);
    }

    public function testDurationBecomesHistogram(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, new InstrumentCache(64), []);
        $duration = Measurement::duration(
            MeasurementName::fromString('latency'),
            ExecutionId::fromString('exec-1'),
            12.5
        );

        $mapper->record($duration);
        $metric = $this->kit->metric('latency');

        self::assertNotNull($metric);
        $data = $metric->data;
        self::assertInstanceOf(Histogram::class, $data);
        $point = null;
        foreach ($data->dataPoints as $candidate) {
            $point = $candidate;
            break;
        }

        self::assertNotNull($point);
        self::assertSame(12.5, $point->sum);
    }

    public function testAttributeAllowListRestrictsDimensions(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper(
            $this->kit->meterProvider,
            new InstrumentCache(64),
            ['region'],
        );
        $measurement = Measurement::counter(
            MeasurementName::fromString('requests'),
            ExecutionId::fromString('exec-1'),
            3,
            instant: Instant::of(1_500),
            attributes: AttributeCollection::fromArray(['region' => 'eu', 'tenant.id' => 'acme']),
        );

        $mapper->record($measurement);
        $metric = $this->kit->metric('requests');

        self::assertNotNull($metric);
        $attributes = $this->firstAttributes($metric);
        self::assertSame('eu', $attributes['region'] ?? null);
        self::assertArrayNotHasKey('tenant.id', $attributes);
    }

    public function testEmptyAllowListDropsAllDimensions(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, new InstrumentCache(64), []);
        $measurement = Measurement::counter(
            MeasurementName::fromString('requests'),
            ExecutionId::fromString('exec-1'),
            3,
            instant: Instant::of(1_500),
            attributes: AttributeCollection::fromArray(['tenant.id' => 'acme']),
        );

        $mapper->record($measurement);
        $metric = $this->kit->metric('requests');

        self::assertNotNull($metric);
        $attributes = $this->firstAttributes($metric);
        self::assertArrayNotHasKey('tenant.id', $attributes);
    }

    public function testInstrumentCacheIsBounded(): void
    {
        $cache = new InstrumentCache(2);
        $mapper = new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, $cache, []);

        $mapper->record(Measurement::counter(MeasurementName::fromString('a'), ExecutionId::fromString('exec-1'), 1));
        $mapper->record(Measurement::counter(MeasurementName::fromString('b'), ExecutionId::fromString('exec-1'), 1));
        $mapper->record(Measurement::counter(MeasurementName::fromString('c'), ExecutionId::fromString('exec-1'), 1));

        self::assertTrue($cache->has('c|count|counter'));
        self::assertSame(2, $cache->size());
    }

    public function testMissingMeterProviderRecordsNothing(): void
    {
        $mapper = new DefaultMeasurementInstrumentMapper(
            meterProvider: null,
            cache: new InstrumentCache(64),
            metricAttributeAllowList: [],
        );
        $mapper->record(Measurement::counter(
            MeasurementName::fromString('items'),
            ExecutionId::fromString('exec-1'),
            5
        ));

        self::assertCount(0, $this->kit->collectMetrics());
    }

    public function testPublisherWiresPayloadFreeHealthForNonnumericValue(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            /** @var list<array{string, array<string, mixed>}> */
            public array $reports = [];

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->reports[] = [$error->getMessage(), $artifact->normalize()];
            }
        };
        $publisher = TelemetryPublisher::fromConfig(
            BridgeConfig::builder()->withMeterProvider($this->kit->meterProvider)->build(),
            health: $health,
        );
        $publisher->publish(new RedactedArtifact([
            'name' => 'private_metric', 'value' => 'secret-value', 'id' => 'secret-id',
        ], 'measurement'));

        self::assertSame([[
            'Redacted measurement has a nonnumeric value.',
            ['id' => 'redacted', 'type' => 'runit.artifact.redacted'],
        ]], $health->reports);
        self::assertNull($this->kit->metric('private_metric'));
    }

    public function testHealthFailureAndReentryAreContainedWithPayloadFreeFallback(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public ?DefaultMeasurementInstrumentMapper $mapper = null;

            public int $calls = 0;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                $this->mapper?->mapRedactedMeasurement(['name' => 'nested', 'value' => 'nested-secret']);
                throw new RuntimeException('reporter-secret');
            }
        };
        $mapper = new DefaultMeasurementInstrumentMapper(
            $this->kit->meterProvider,
            new InstrumentCache(64),
            [],
            $health,
        );
        $health->mapper = $mapper;
        $log = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($log);
        $previous = ini_get('error_log');
        ini_set('error_log', $log);
        try {
            $mapper->mapRedactedMeasurement(['name' => 'metric', 'value' => 'payload-secret']);
            $mapper->mapRedactedMeasurement(['name' => 'metric', 'value' => 'payload-secret']);
            $contents = file_get_contents($log);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($log);
        }

        self::assertSame(2, $health->calls);
        self::assertIsString($contents);
        self::assertSame(2, substr_count($contents, 'Redacted measurement has a nonnumeric value.'));
        self::assertStringNotContainsString('secret', $contents);
        self::assertNull($this->kit->metric('metric'));
    }

    public function testMissingHealthUsesFallbackButDisabledProviderDoesNotReport(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($log);
        $previous = ini_get('error_log');
        ini_set('error_log', $log);
        try {
            new DefaultMeasurementInstrumentMapper(null, new InstrumentCache(64), [])
                ->mapRedactedMeasurement(['name' => 'metric', 'value' => 'secret'])
            ;
            new DefaultMeasurementInstrumentMapper($this->kit->meterProvider, new InstrumentCache(64), [])
                ->mapRedactedMeasurement(['name' => 'metric', 'value' => 'secret'])
            ;
            $contents = file_get_contents($log);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($log);
        }

        self::assertIsString($contents);
        self::assertSame(1, substr_count($contents, 'Redacted measurement has a nonnumeric value.'));
        self::assertStringNotContainsString('secret', $contents);
    }
}
