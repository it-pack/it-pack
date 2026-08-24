# Recipe: OpenTelemetry

Export RunIt artifacts as OpenTelemetry signals by using `TelemetryPublisher` as the
runtime's publisher. Executions and activities become spans; measurements become
metrics; observations become log records. The bridge never configures global
providers — you inject them through `BridgeConfigBuilder`.

Requires `open-telemetry/api` (mapping) and, for real export, `open-telemetry/sdk`.
See [`../integrations/opentelemetry.md`](../integrations/opentelemetry.md) and
`examples/opentelemetry.php` for the full wiring.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$config = (new BridgeConfigBuilder())
    ->withTracerProvider(new TracerProvider(new SimpleSpanProcessor(ConsoleSpanExporter::create())))
    ->withMetricAttributeAllowList(['region']) // keep cardinality low
    ->build();

$runtime = RunItBuilder::create()
    ->withPublisher(TelemetryPublisher::fromConfig($config))
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->measurements()->counter(
            \ItPack\RunIt\Domain\Measurement\MeasurementName::fromString('items'),
            3,
            \ItPack\RunIt\Domain\Measurement\MeasurementUnit::Count,
        );
    },
);
```

A `null` provider disables that signal without error, so you can enable only the
signals you need. Cardinality is controlled by `withMetricAttributeAllowList`; secret
context is never propagated.

## Redaction

The builder composes redaction before the bridge: by default a deny-by-default
redactor masks throwable messages, and you can supply a key-driven strategy for
occurrence/snapshot payloads:

```php
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;

$runtime = RunItBuilder::create()
    // ...
    ->withRedactor(new DefaultArtifactRedactor(new ArtifactRedactionConfig(
        sensitiveDataKeys: ['token', 'password'],
    )))
    ->build();
```

To publish unredacted (not recommended), opt out explicitly with
`withRedactor(null)` — the choice is recorded rather than falling back to
defaults. Occurrence extension attributes exported to logs can additionally be
restricted through `BridgeConfigBuilder::withOccurrenceLogAttributeAllowList()`.
