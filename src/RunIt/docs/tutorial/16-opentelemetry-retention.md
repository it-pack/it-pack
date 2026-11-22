# Tutorial 16 — OpenTelemetry span-context retention

**Goal:** understand how RunIt bounds the OpenTelemetry span-context store, why
eviction matters for long-lived workers, and how to configure or replace the
default store (Phase 5).

RunIt's OpenTelemetry bridge remembers the span context produced for each
execution and activity so child spans can be wired to the correct parent. Without
a bound, that store grows without limit in a long-lived worker. Phase 5 makes the
default store finite and configurable.

## The default store

`TelemetryPublisher::fromConfig()` creates an in-memory store by default, shared
by its execution and activity mappers. It retains **contexts, not live spans**,
with a default limit of **10,000 entries across executions and activities
combined**. On overflow the oldest entry is evicted (oldest-write-first).
Replacing an existing ID makes it newest; lookup does not refresh its age.

```php
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$config = (new BridgeConfigBuilder())
    ->withTracerProvider($tracerProvider)
    ->build();

$otelPublisher = TelemetryPublisher::fromConfig($config); // default: 10,000 entries
```

Execution and activity IDs occupy separate namespaces (`e:` / `a:` prefixes),
but activity IDs are **not scoped by execution** — a reused activity ID replaces
the previous context regardless of which execution owns it.

## Choosing a different capacity

Size the store against worker memory and expected parent delay. Pass a positively
sized `InMemoryExecutionSpanStore` as the second argument to `fromConfig()`:

```php
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$otelPublisher = TelemetryPublisher::fromConfig(
    $config,
    new InMemoryExecutionSpanStore(20000),
);
```

The constructor rejects capacities below 1 with `InvalidArgumentException`.

## Injecting a custom store

For full control over retention, duplicate handling, and concurrency, inject a
custom `ExecutionSpanStoreInterface`:

```php
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$otelPublisher = TelemetryPublisher::fromConfig($config, $customStore);
```

Custom stores define their own policy. The interface supplies no removal or
capacity configuration contract — that is the implementation's responsibility.

## What eviction changes

A parent lookup can return `null` even if the parent was previously recorded,
once its entry has been evicted. In that case the bridge does not set an explicit
parent; the OpenTelemetry builder may use the ambient context instead. A later
parent does not reparent an already exported span.

This means upgrading from the formerly unbounded default can change correlation
for late children. Configure a larger store or inject a custom implementation if
the default retention window is insufficient.

Default stores are isolated per publisher: separate `fromConfig()` calls without
an injected store create separate stores and do not share parent correlation,
even when they use the same tracer provider. Share one injected store between
publishers only when you intentionally want shared correlation state.

## Running the example

The bundled example writes 50,000 execution/activity pairs through a store with
20,000 capacity and confirms the oldest entries are evicted while recent ones
remain:

```bash
php examples/opentelemetry-retention.php
```

## Where to go next

- [`docs/integrations/opentelemetry.md#span-context-retention`](../integrations/opentelemetry.md)
  — operational guidance and cardinality considerations.
- [`docs/recipes/opentelemetry.md`](../recipes/opentelemetry.md) — bounded
  retention in the context of a full bridge wiring.
- [`examples/opentelemetry-retention.php`](../../examples/opentelemetry-retention.php)
  — runnable demonstration of custom capacity and eviction.
