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

## Span context retention

`TelemetryPublisher::fromConfig()` creates an in-memory store by default, shared by
its execution and activity mappers. It retains contexts, not live spans, in separate
ID-keyed maps with no limit, removal, or eviction. A long-lived publisher can therefore
grow with the number of distinct execution and activity IDs. Reusing an ID replaces the
previous context in that map; activity IDs are not scoped by execution. The maps live
as long as their store remains reachable. A null tracer provider records nothing.

An application can pass an `ExecutionSpanStoreInterface` as the second argument to
`fromConfig()`. Both mappers use that exact store; sharing it between publishers also
shares correlation and its lifetime. Custom stores define their own retention, duplicate
handling, and concurrency properties. The interface permits eviction, but supplies no
removal or capacity configuration contract.

Each mapper looks up a parent before starting its span and records its own context
after ending the span. Child executions look up their parent execution. Activities look
up their parent activity first, then their execution if that lookup misses. If no stored
parent is found, the bridge does not set an explicit parent; the OpenTelemetry builder
may use the ambient context. A later parent does not reparent an already exported span.
Evicting a context in a custom store can consequently alter linkage for late children.

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

The bridge receives the artifact supplied by its publisher. `RunItBuilder`
installs a default redactor unless you opt out with `withRedactor(null)` or
supply a custom strategy, but the default is not a comprehensive sanitizer.
In particular, it does not mask failed-execution throwables nested under
`result.throwable`; free-form messages and unconfigured payload keys can also
contain sensitive data. Apply and verify independent sanitization before data
reaches the bridge or a sampler. See [current publication limitations](../SECURITY.md#current-publication-limitations).

Payload-key masking for occurrence `data` and snapshot `payload` can be
configured explicitly, subject to the limitations linked above:

```php
$runtime = RunItBuilder::create()
    ->withPublisher(TelemetryPublisher::fromConfig($config))
    ->withRedactor(new DefaultArtifactRedactor(new ArtifactRedactionConfig(
        sensitiveDataKeys: ['token', 'password'],
    )))
    // ... required ports ...
    ->build()
;
```

The builder composes redaction closest to the sink and sampling outside it;
consequently, configured samplers receive the original artifact. When redaction
changes an artifact, the resulting generic projection does not necessarily follow
the same adapter path as the original typed artifact. Do not assume that all
fields reaching an exporter have been masked or that every redacted artifact is
delivered.
In a test with a configured sensitive attribute, an observation changed by the
default redactor produces neither an OpenTelemetry log nor a span event, whereas
an unredacted control produces both. This characterizes the tested paths, not all
possible redacted artifact shapes.

## Fiber considerations

The bridge uses an explicit ID-keyed store for known parents, rather than relying on
ambient context for those lookups. IDs must be suitably unique within a shared store:
duplicates can replace another execution's or activity's context. Missing lookups can
fall back to ambient OpenTelemetry context, whose isolation depends on its configured
context storage. The default store provides no explicit synchronization or per-Fiber
partitioning; assess custom stores and concurrent runtime use independently.

## Example

See `examples/opentelemetry.php` for an executable end-to-end wiring that publishes a
small execution, activity, measurement, observation, occurrence, and snapshot through
in-memory exporters and prints the exported signals.
