# Tutorial 7 — Publish & integrate

**Goal:** move artifacts out of the runtime and into the systems you already use.

So far every example used `InMemoryArtifactPublisher`, which keeps artifacts in memory
so you can inspect them. Production needs a publisher that actually delivers them.

## Inspecting what was produced

The in-memory publisher is also the test harness the suite uses:

```php
$publisher = new InMemoryArtifactPublisher();
/* build runtime with $publisher ... */

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->observations()->record('payment accepted', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
    },
);

echo 'published artifact count: ' . count($publisher->published()) . PHP_EOL;
foreach ($publisher->published() as $artifact) {
    echo '  - ' . $artifact->normalize()['id'] . PHP_EOL;
}
```

> **Run this:** [`examples/in-memory-runtime.php`](https://github.com/it-pack/run-it/blob/main/examples/in-memory-runtime.php).

## Choosing an integration

RunIt's adapters are optional and sit at the edge — the domain never depends on them.
Pick what you need:

| I want to… | Start here |
|---|---|
| Emit logs / events via PSR-3 or PSR-14 | [integrations/psr.md](../integrations/psr.md) · [recipe: psr-3](../../recipes/psr-3.md) |
| Send traces/metrics/logs to OpenTelemetry | [integrations/opentelemetry.md](../integrations/opentelemetry.md) · [examples/opentelemetry.php](https://github.com/it-pack/run-it/blob/main/examples/opentelemetry.php) |
| Stream facts as CloudEvents | [integrations/cloudevents.md](../integrations/cloudevents.md) · [examples/cloudevents.php](https://github.com/it-pack/run-it/blob/main/examples/cloudevents.php) |
| Handle HTTP requests | [integrations/README.md](../integrations/README.md) · [recipe: http-request](../../recipes/http-request.md) |
| Run under Swoole / OpenSwoole | [integrations/swoole.md](../integrations/swoole.md) · [examples/swoole.php](https://github.com/it-pack/run-it/blob/main/examples/swoole.php) |
| Isolate context per Fiber / coroutine | [integrations/fibers.md](../integrations/fibers.md) · [examples/fibers.php](https://github.com/it-pack/run-it/blob/main/examples/fibers.php) |

## A taste: OpenTelemetry

The OpenTelemetry bridge maps executions→traces, activities→spans, measurements→metrics,
observations→logs, and occurrences→events. You wire vendor exporters and hand the
configured publisher to RunIt:

```php
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;

$config = (new BridgeConfigBuilder())
    ->withTracerProvider($tracerProvider)
    ->withMeterProvider($meterProvider)
    ->withLoggerProvider($loggerProvider)
    ->withMetricAttributeAllowList(['region'])
    ->build();

$otelPublisher = TelemetryPublisher::fromConfig($config);
// $otelPublisher implements ArtifactPublisherInterface; pass it to withPublisher()
```

> The full, runnable version is
> [`examples/opentelemetry.php`](https://github.com/it-pack/run-it/blob/main/examples/opentelemetry.php).
> Note the `withMetricAttributeAllowList(['region'])` — cardinality is bounded by default.

## A taste: CloudEvents

Occurrences serialize cleanly to CloudEvents. The bridge maps both directions and
preserves unknown extension attributes:

```php
use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;

$publisher = CloudEventPublisher::fromConfig(
    CloudEventsBridgeConfig::builder()
        ->withSink(new CallbackCloudEventSink(static function ($event) use ($serializer): void {
            echo $serializer->serialize($event) . PHP_EOL;
        }))
        ->build(),
);
$publisher->publish($occurrence);
```

> Full version: [`examples/cloudevents.php`](https://github.com/it-pack/run-it/blob/main/examples/cloudevents.php).

## Publication guarantees

Whatever adapter you choose, the pipeline is the same: validate → enrich → classify →
redact → sample → route → publish. Redaction happens *before* export; failures are
fails-open with fallback reporting by default; and a recursion guard stops publication
from observing itself. See [architecture/publication.md](../architecture/publication.md).

## Where to go now

- Browse the [Recipes](../README.md#recipes) for your exact stack (CLI, queue, worker,
  scheduler, workflow, testing).
- Build an adapter or consume RunIt from a library: [recipes/third-party-adapter.md](../../recipes/third-party-adapter.md)
  and [conformance.md](../../conformance.md).
- Decide fit and deployment shape in [Use cases](../use-cases.md).
