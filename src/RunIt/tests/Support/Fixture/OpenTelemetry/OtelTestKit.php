<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\OpenTelemetry;

use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogInMemoryExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Metrics\Data\Metric;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as MetricInMemoryExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as TraceInMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Wires real OpenTelemetry SDK providers backed by in-memory exporters so tests
 * can assert on exported spans, metrics, and logs without network or stdout.
 */
final class OtelTestKit
{
    public TraceInMemoryExporter $spanExporter;

    public MetricInMemoryExporter $metricExporter;

    public LogInMemoryExporter $logExporter;

    public TracerProvider $tracerProvider;

    public LoggerProviderInterface $loggerProvider;

    public MeterProviderInterface $meterProvider;

    private ExportingReader $metricReader;

    public function __construct()
    {
        $this->spanExporter = new TraceInMemoryExporter();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->spanExporter));

        $this->metricExporter = new MetricInMemoryExporter();
        $this->metricReader = new ExportingReader($this->metricExporter);
        $this->meterProvider = new MeterProviderBuilder()->addReader($this->metricReader)->build();

        $this->logExporter = new LogInMemoryExporter();
        $this->loggerProvider = new LoggerProviderBuilder()
            ->addLogRecordProcessor(new SimpleLogRecordProcessor($this->logExporter))
            ->build()
        ;
    }

    /**
     * @return SpanDataInterface[]
     */
    public function spans(): array
    {
        /** @var SpanDataInterface[] $spans */
        $spans = $this->spanExporter->getSpans();

        return $spans;
    }

    public function log(int $index): ReadableLogRecord
    {
        /** @var ReadableLogRecord $record */
        $record = $this->logExporter->getStorage()->offsetGet($index);

        return $record;
    }

    /**
     * @return Metric[]
     */
    public function metrics(): array
    {
        $this->metricReader->collect();
        /** @var Metric[] $metrics */
        $metrics = $this->metricExporter->collect(true);

        return $metrics;
    }

    public function metric(string $name): ?Metric
    {
        foreach ($this->metrics() as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * @return \OpenTelemetry\SDK\Metrics\Data\Metric[]
     */
    public function collectMetrics(): array
    {
        $this->metricReader->collect();

        return $this->metricExporter->collect(true);
    }

    /**
     * @return \ArrayObject<int, \OpenTelemetry\SDK\Logs\ReadableLogRecord>
     */
    public function logs(): \ArrayObject
    {
        return $this->logExporter->getStorage();
    }
}
