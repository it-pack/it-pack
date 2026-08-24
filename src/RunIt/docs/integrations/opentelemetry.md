# OpenTelemetry integration

RunIt models execution with its own vendor-neutral vocabulary (execution, activity,
observation, occurrence, snapshot, measurement). The bridge in
`src/Adapter/OpenTelemetry/` translates that vocabulary to OpenTelemetry signals
(traces, metrics, logs) so RunIt data can be exported through the OpenTelemetry PHP
SDK without coupling the core to it.

The bridge follows the same "vendored adapter" pattern as the PSR adapters: the core
stays usable without OpenTelemetry, and consumers opt into the dependency only when
they wire the bridge. See `docs/adr/ADR-020-opentelemetry-bridge-placement.md`.

## Required optional packages

| Signal | Package | Required |
|--------|---------|----------|
| Mapping (traces, metrics, logs, propagation) | `open-telemetry/api` | Yes, when using the bridge |
| Real exporters (OTLP, stdout, etc.) | `open-telemetry/sdk` | Only for actual export |

`open-telemetry/api` is a `require-dev` dependency; `open-telemetry/sdk` is used only
by tests and examples. The bridge **never** configures global OpenTelemetry providers.
Every provider is injected through `BridgeConfig`.

## Conceptual mappings

| RunIt concept | OpenTelemetry signal |
|---------------|----------------------|
| Execution (root) | Root span, kind derived from execution kind |
| Execution (child) | Child span of the parent execution's span |
| Activity | Span parented to its execution or parent activity |
| Execution/Activity status | `Ok` for succeeded, `Error` (with reason) for failed, cancelled, timed_out |
| Throwable | `exception` span event on the failing span |
| Measurement (counter, size) | `Counter` |
| Measurement (gauge) | `Gauge` |
| Measurement (duration, ratio) | `Histogram` |
| Observation | Log record (severity, message, attributes, correlation) |
| Occurrence / Snapshot | Log record and/or span event under policy |

Secret context entries are never propagated, and high-cardinality dimensions
(ids, correlation) are excluded from metric attributes by default.

## Installation

```bash
composer require open-telemetry/api
# add a concrete exporter when you want real export:
composer require open-telemetry/sdk
```

## Provider responsibility

The application owns provider construction and export configuration. The bridge only
consumes what it is given:

```php
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\MetricExporter\ConsoleMetricExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporter;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$tracerProvider = new TracerProvider(new SimpleSpanProcessor(ConsoleSpanExporter::create()));
$meterProvider  = (new MeterProviderBuilder())->addReader(new ExportingReader(ConsoleMetricExporter::create()))->build();
$loggerProvider = (new LoggerProviderBuilder())->addLogRecordProcessor(new SimpleLogRecordProcessor(ConsoleExporter::create()))->build();

$config = (new BridgeConfigBuilder())
    ->withTracerProvider($tracerProvider)
    ->withMeterProvider($meterProvider)
    ->withLoggerProvider($loggerProvider)
    ->build();

$publisher = TelemetryPublisher::fromConfig($config);
```

A `null` provider disables that signal without error, so a consumer can enable only the
signals it needs.

## Cardiality and payload controls

- `withMetricAttributeAllowList(['region'])` — only listed keys become metric
  dimensions; everything else (including ids) is dropped by default.
- `withOccurrenceLogAttributeAllowList([...])` — restricts which occurrence
  extension attributes are exported to logs. An empty list (the default) keeps
  the unrestricted copy, which is bounded upstream by occurrence validation; a
  non-empty list exports only the listed keys.
- `withSnapshotPayloadPolicy(...)` — snapshots export identity, type, and content hash
  by default; the full payload is exported only under `SnapshotPayloadPolicy::Full`.
- Instrument objects are cached by a stable identity and the cache is bounded
  (`withMaxInstrumentCacheSize`).

## Redaction before export

The bridge exports whatever artifact reaches it, so redaction must be composed
*before* the bridge. The SDK builder does this for you: since Phase 1 of the
2026-08-24 plan, `RunItBuilder` installs a deny-by-default redactor that masks
throwable messages unless you explicitly opt out with `withRedactor(null)` or
supply a custom strategy. Payload masking (occurrence `data`, snapshot
`payload`) is key-driven — configure a custom redactor:

```php
$runtime = RunItBuilder::create()
    ->withPublisher(new TelemetryPublisher::fromConfig($bridgeConfig))
    ->withRedactor(new DefaultArtifactRedactor(new ArtifactRedactionConfig(
        sensitiveDataKeys: ['token', 'password'],
    )))
    // ... required ports ...
    ->build()
;
```

The builder composes redaction closest to the sink and sampling outside it, so
dropped artifacts are never serialized and exported artifacts are already
masked.

## Fiber considerations

The bridge keeps correlation in an explicit, id-keyed span store rather than in global
mutable context, so invoking the publisher from multiple Fibers cannot leak one
execution's parent linkage into another. Fiber-aware OpenTelemetry context storage is
optional; when it is unavailable the bridge still works because correlation is carried
by RunIt artifact identity, not by ambient context.

## Example

See `examples/opentelemetry.php` for an executable end-to-end wiring that publishes a
small execution, activity, measurement, observation, occurrence, and snapshot through
in-memory exporters and prints the exported signals.
