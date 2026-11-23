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

| Signal                                       | Package              | Required                   |
|----------------------------------------------|----------------------|----------------------------|
| Mapping (traces, metrics, logs, propagation) | `open-telemetry/api` | Yes, when using the bridge |
| Real exporters (OTLP, stdout, etc.)          | `open-telemetry/sdk` | Only for actual export     |

`open-telemetry/api` is a `require-dev` dependency; `open-telemetry/sdk` is used only
by tests and examples. The bridge **never** configures global OpenTelemetry providers.
Every provider is injected through `BridgeConfig`.

## Conceptual mappings

| RunIt concept                 | OpenTelemetry signal                                                       |
|-------------------------------|----------------------------------------------------------------------------|
| Execution (root)              | Root span, kind derived from execution kind                                |
| Execution (child)             | Child span of the parent execution's span                                  |
| Activity                      | Span parented to its execution or parent activity                          |
| Execution/Activity status     | `Ok` for succeeded, `Error` (with reason) for failed, cancelled, timed_out |
| Throwable                     | `exception` span event on the failing span                                 |
| Measurement (counter, size)   | `Counter`                                                                  |
| Measurement (gauge)           | `Gauge`                                                                    |
| Measurement (duration, ratio) | `Histogram`                                                                |
| Observation                   | Log record (severity, message, attributes, correlation)                    |
| Occurrence / Snapshot         | Log record and/or span event under policy                                  |

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
its execution and activity mappers. It retains contexts, not live spans, with a default
limit of 10,000 entries across executions and activities combined. On overflow the
oldest entry is evicted; replacing an existing ID makes it newest, but lookup does not
refresh its age. Execution and activity IDs occupy separate namespaces, but activity
IDs are not scoped by execution. A null tracer provider records nothing.
Separate calls to `fromConfig()` without an injected store create separate stores;
they do not share parent correlation, even when they use the same tracer provider.

Sequential characterization on 2026-11-22 with PHP 8.5.13 and Codeception 5.3.7
passed `testDefaultStoresAreIsolatedAndRetainDelayedParents` (1 test, 5 assertions):
one publisher correlated a child after 100 intervening execution publications,
while a second default publisher did not correlate the same parent ID. This is not
a memory bound, concurrency guarantee, or representative worker workload.

An application can pass an `ExecutionSpanStoreInterface` as the second argument to
`fromConfig()`. Both mappers use that exact store; sharing it between publishers also
shares correlation and its lifetime. Custom stores define their own retention, duplicate
handling, and concurrency properties. The interface permits eviction, but supplies no
removal or capacity configuration contract.

To choose a different positive entry limit for the built-in store:

```php
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$publisher = TelemetryPublisher::fromConfig($config, new InMemoryExecutionSpanStore(20000));
```

The limit counts entries, not bytes: IDs and contexts can vary in size. Size it against
worker memory and delayed-parent workloads. The default in-memory implementation does
not promise thread or coroutine isolation; use an appropriately isolated or synchronized
custom store when required.

For a reproducible synthetic comparison against the historical unbounded store,
including measured delayed-parent loss and its limitations, see
[benchmarks](../benchmarks.md#historical-store-comparison-2026-11-22). It is not
a substitute for measuring the application's own worker memory and parent delays.

For a hands-on walkthrough of bounded retention, custom capacity, and custom
store injection, see
[Tutorial 16 — OpenTelemetry span-context retention](../tutorial/16-opentelemetry-retention.md)
and [`examples/opentelemetry-retention.php`](../../examples/opentelemetry-retention.php).

Each mapper looks up a parent before starting its span and records its own context
after ending the span. Child executions look up their parent execution. Activities look
up their parent activity first, then their execution if that lookup misses. If no stored
parent is found, the bridge does not set an explicit parent; the OpenTelemetry builder
may use the ambient context. A later parent does not reparent an already exported span.
Eviction can consequently alter linkage for late children. Upgrading from the formerly
unbounded default can change correlation; configure a larger store or inject a custom
implementation if the default retention window is insufficient.

## Cardiality and payload controls

- `withMetricAttributeAllowList(['region'])` — only listed keys become metric
  dimensions; everything else (including ids) is dropped by default.
- `withOccurrenceLogAttributeAllowList([...])` — restricts which occurrence
  extension attributes are exported to logs. An empty list (the default) keeps
  the unrestricted copy, which is bounded upstream by occurrence validation; a
  non-empty list exports only the listed keys.
- `withSnapshotPayloadPolicy(...)` — snapshots export identity, type, and content hash
  by default; `None` omits the hash, including for redacted snapshots. Hash-enabled
  policies export the original hash, which may reveal information about the payload.
  The full payload is exported only under `SnapshotPayloadPolicy::Full` for typed snapshots.
  Redacted snapshot logs use the normalized `created_at` timestamp; missing or
  non-integer values fall back to the current time.
- Instrument objects are cached by a stable identity and the cache is bounded
  (`withMaxInstrumentCacheSize`).

## Redaction before export

The bridge receives the artifact supplied by its publisher. `RunItBuilder`
installs a default redactor unless you opt out with `withRedactor(null)` or
supply a custom strategy, but the default is not a comprehensive sanitizer.
In particular, free-form messages and unconfigured payload keys can contain
sensitive data, and custom throwable projections can bypass recognized fields.
Apply and verify independent sanitization before data
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

The default builder stack applies redaction before sampling; consequently,
configured samplers receive the redacted artifact. When redaction
changes an artifact, the resulting generic projection does not necessarily follow
the same adapter path as the original typed artifact. Do not assume that all
fields reaching an exporter have been masked or that every redacted artifact is
delivered.
In a test with a configured sensitive attribute, an observation changed by the
default redactor produces neither an OpenTelemetry log nor a span event, whereas
an unredacted control produces both. This characterizes the tested paths, not all
possible redacted artifact shapes.
In a separate test with enabled providers, a redacted activity and measurement
also produced no span or metric, while unchanged typed controls did. The tested
redactor masked the configured attribute without mutating either original. Do
not rely on this observation as a delivery guarantee or a comprehensive security
assessment; routing and visible reporting for these projections remain open.

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
