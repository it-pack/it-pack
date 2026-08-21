<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\OpenTelemetryBridgeInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\InMemoryTelemetrySink;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OtelTelemetrySink;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Execution\ExecutionContext;

/**
 * Configuration is observable.
 *
 * Built on the Execution reporter spine, OpenTelemetryReporter translates the
 * resolution Occurrences/Observations into traces, configuration lifecycle
 * events and metrics. Core never depends on the OpenTelemetry SDK: a consumer
 * supplies the transport behind OpenTelemetryBridgeInterface.
 *
 * This example first inspects the produced telemetry with the offline
 * InMemoryTelemetrySink, then shows the real-SDK seam via a stub bridge.
 *
 * Run from the package root:
 *   php examples/08-observability.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// --- Part 1: inspect telemetry offline (no SDK, no network) -----------------

$sink = new InMemoryTelemetrySink();

$resolver = new ConfigurationBuilder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Formats'))
    ->addLoaders(ConfigurationLoaders::default())
    ->withExecutionContext(new ExecutionContext('production', ['tenant' => 'acme']))
    ->withTelemetry($sink)
    ->build();

$resolver->resolve();

echo '== Trace ==' . PHP_EOL;
foreach ($sink->traces() as $trace) {
    if ($trace['name'] === 'end') {
        continue;
    }
    printf('  %s (%s)' . PHP_EOL, $trace['name'], $trace['id']);
    foreach ($trace['attributes'] as $key => $value) {
        printf('    %s = %s' . PHP_EOL, $key, var_export($value, true));
    }
}

echo PHP_EOL . '== Spans (one per lifecycle stage) ==' . PHP_EOL;
foreach ($sink->spans() as $span) {
    echo '  - ' . $span['name'] . PHP_EOL;
}

echo PHP_EOL . '== Lifecycle events ==' . PHP_EOL;
foreach ($sink->events() as $event) {
    echo '  - ' . $event['name'] . PHP_EOL;
}

echo PHP_EOL . '== Metrics ==' . PHP_EOL;
foreach ($sink->measures() as $measure) {
    printf('  %s = %s' . PHP_EOL, $measure['name'], var_export($measure['value'], true));
}
foreach ($sink->counters() as $counter) {
    printf('  %s (+%d)' . PHP_EOL, $counter['name'], $counter['value']);
}

// --- Part 2: the real OpenTelemetry SDK seam (stub bridge) -----------------

/**
 * A consumer implements this once, against open-telemetry/opentelemetry-api,
 * and ConfigureIt owns only the telemetry *semantics* (SemanticConventions).
 */
final class PrintingBridge implements OpenTelemetryBridgeInterface
{
    public function startTrace(string $name, array $attributes): string
    {
        echo '  [bridge] startTrace ' . $name . PHP_EOL;

        return 'trace-x';
    }

    public function endTrace(string $traceId, array $attributes): void
    {
        echo '  [bridge] endTrace ' . $traceId . PHP_EOL;
    }

    public function span(string $traceId, string $name, array $attributes, ?float $startedAt, ?float $endedAt): void
    {
        echo '  [bridge] span ' . $name . PHP_EOL;
    }

    public function event(string $traceId, string $name, array $attributes): void
    {
        echo '  [bridge] event ' . $name . PHP_EOL;
    }

    public function increment(string $name, array $attributes, int $value): void
    {
        echo '  [bridge] increment ' . $name . PHP_EOL;
    }

    public function measure(string $name, float $value, array $attributes): void
    {
        echo '  [bridge] measure ' . $name . ' = ' . $value . PHP_EOL;
    }
}

echo PHP_EOL . '== Wiring the real SDK (stub bridge prints calls) ==' . PHP_EOL;

$bridgeSink = new OtelTelemetrySink(new PrintingBridge());

$resolver = new ConfigurationBuilder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Formats'))
    ->addLoaders(ConfigurationLoaders::default())
    ->withTelemetry($bridgeSink)
    ->build();

$resolver->resolve();
