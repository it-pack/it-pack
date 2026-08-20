<?php

declare(strict_types=1);

/**
 * RunIt OpenTelemetry bridge — executable example.
 *
 * Wires the bridge with in-memory OpenTelemetry exporters, publishes a small
 * execution with an activity, a measurement, an observation, an occurrence, and a
 * snapshot, then prints the exported signals.
 *
 * Run from the package root: `php examples/opentelemetry.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogInMemoryExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as MetricInMemoryExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as TraceInMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$spanExporter = new TraceInMemoryExporter();
$metricExporter = new MetricInMemoryExporter();
$logExporter = new LogInMemoryExporter();

$tracerProvider = new TracerProvider(new SimpleSpanProcessor($spanExporter));
$metricReader = new ExportingReader($metricExporter);
$meterProvider = new MeterProviderBuilder()
    ->addReader($metricReader)
    ->build();
$loggerProvider = new LoggerProviderBuilder()
    ->addLogRecordProcessor(new SimpleLogRecordProcessor($logExporter))
    ->build();

$config = new BridgeConfigBuilder()
    ->withTracerProvider($tracerProvider)
    ->withMeterProvider($meterProvider)
    ->withLoggerProvider($loggerProvider)
    ->withMetricAttributeAllowList(['region'])
    ->build();

$otelPublisher = TelemetryPublisher::fromConfig($config);

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$result = $runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('checkout.process'),
        ExecutionKind::fromString('operation'),
    ),
    static function (): array {
        return ['status' => 'ok'];
    },
);

// Publish a representative set of RunIt artifacts through the OpenTelemetry bridge.
try {
    $otelPublisher->publish(Execution::start(
        ExecutionId::fromString('exec-1'),
        ExecutionDefinition::create(
            ExecutionName::fromString('checkout.process'),
            ExecutionKind::fromString('operation'),
        ),
        Instant::now(),
    ));
} catch (Throwable $e) {

}

try {
    $otelPublisher->publish(Measurement::counter(
        MeasurementName::fromString('items.processed'),
        ExecutionId::fromString('exec-1'),
        3,
        null,
        null,
        null,
        AttributeCollection::fromArray(['region' => 'eu']),
    ));
} catch (Throwable $e) {

}

try {
    $otelPublisher->publish(Observation::info(
        ObservationId::fromString('obs-1'),
        ExecutionId::fromString('exec-1'),
        'checkout completed',
    ));
} catch (Throwable $e) {

}

try {
    $otelPublisher->publish(Occurrence::create(
        OccurrenceId::fromString('occ-1'),
        ExecutionId::fromString('exec-1'),
        OccurrenceType::fromString('checkout.completed.v1'),
        'it-pack/example',
        Instant::now(),
    ));
} catch (Throwable $e) {

}

try {
    echo 'Execution result: ' . json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (JsonException $e) {

}
echo 'Exported spans: ' . count($spanExporter->getSpans()) . PHP_EOL;
echo 'Exported logs: ' . $logExporter->getStorage()->count() . PHP_EOL;

$metricReader->collect();
foreach ($metricExporter->collect(true) as $metric) {
    echo 'Exported metric: ' . $metric->name . PHP_EOL;
}
