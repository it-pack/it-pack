# Recipe: CloudEvents

Publish RunIt occurrences as CloudEvents using `CloudEventPublisher`. The bridge is
SDK-free: it implements the CloudEvents 1.0 structured JSON representation directly,
so no `cloudevents/sdk-php` dependency is required for mapping or serialization. Only
HTTP delivery needs a PSR-18 client. See
[`../integrations/cloudevents.md`](../integrations/cloudevents.md).

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$captured = [];
$publisher = CloudEventPublisher::fromConfig(
    CloudEventsBridgeConfig::builder()
        ->withSink(new CallbackCloudEventSink(static function ($event) use (&$captured): void {
            $captured[] = $event; // or serialize and POST via Psr18CloudEventSink
        }))
        ->build(),
);

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('config.resolve'), ExecutionKind::fromString('configuration')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->occurrences()->record(
            \ItPack\RunIt\Domain\Occurrence\OccurrenceType::fromString('config.loaded.v1'),
            'config',
            data: ['files' => ['app/config.yaml']],
        );
    },
);

// $captured now holds CloudEvent objects for each published occurrence
```

Only occurrences are mapped to CloudEvents; executions, activities, measurements, and
observations are exported through the other bridges. Delivery is at-most-once; a
failed `Psr18CloudEventSink` POST is handled by the surrounding publication failure
policy. See [`../integrations/adapter-failure-policies.md`](../integrations/adapter-failure-policies.md).
