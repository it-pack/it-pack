<?php

declare(strict_types=1);

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * RunIt OpenTelemetry bounded span-context retention — executable example.
 *
 * Demonstrates Phase 5: the default in-memory span-context store retains at most
 * 10,000 execution and activity entries combined, evicting the oldest entry on
 * overflow. Replacing an entry refreshes its insertion age; lookups do not.
 *
 * This example shows:
 *   1. Custom capacity via InMemoryExecutionSpanStore (20,000 entries).
 *   2. Custom store injection into TelemetryPublisher::fromConfig().
 *   3. Eviction: after more than capacity writes, the oldest context is dropped
 *      and late children fall back to ambient OpenTelemetry context.
 *
 * Run from the package root: `php examples/opentelemetry-retention.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as TraceInMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$spanExporter = new TraceInMemoryExporter();
$tracerProvider = new TracerProvider(new SimpleSpanProcessor($spanExporter));

// custom capacity — 20,000 entries across executions and activities combined.
$store = new InMemoryExecutionSpanStore(20000);

$config = new BridgeConfigBuilder()
    ->withTracerProvider($tracerProvider)
    ->build()
;

$otelPublisher = TelemetryPublisher::fromConfig($config, $store);

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

try {
    $result = $runtime->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('checkout.process'),
            ExecutionKind::fromString('operation'),
        ),
        static fn (): array => ['status' => 'ok'],
    );


    // Publish 25,000 execution/activity pairs through the bounded store (capacity 20,000).
    // Only the newest 20,000 entries (the last 10,000 pairs) survive; exec-0..exec-14999
    // (15,000 execution contexts) are evicted oldest-write-first.
    for ($i = 0; $i < 25000; ++$i) {
        $executionId = 'exec-' . $i;
        $otelPublisher->publish(Execution::start(
            ExecutionId::fromString($executionId),
            ExecutionDefinition::create(
                ExecutionName::fromString('checkout.process'),
                ExecutionKind::fromString('operation'),
            ),
            Instant::now(),
        ));

        $otelPublisher->publish(Activity::start(
            ActivityId::fromString('act-' . $i),
            ExecutionId::fromString($executionId),
            ActivityDefinition::create(
                ActivityName::fromString('parse'),
                ActivityKind::fromString('internal'),
            ),
            Instant::now(),
        )->complete(ActivityStatus::Succeeded, null, Instant::now()));
    }

} catch (InvalidValueExceptionInterface|Throwable $e) {
    echo $e->getMessage();
    exit(1);
}


// The earliest execution context (exec-0) should have been evicted once we passed
// 20,000 writes (25,000 executions + 25,000 activities = 50,000 > 20,000 capacity).
echo 'Published executions and activities: 50,000' . PHP_EOL;
echo 'Store capacity: 20,000 entries (oldest-write-first eviction)' . PHP_EOL;
echo 'Execution context exec-0 retained: ' . var_export($store->executionContext('exec-0') instanceof SpanContextInterface, true) . PHP_EOL;
echo 'Execution context exec-24999 retained: ' . var_export($store->executionContext('exec-24999') instanceof SpanContextInterface, true) . PHP_EOL;
echo 'Exported spans: ' . count($spanExporter->getSpans()) . PHP_EOL;

echo 'Execution result: ' . json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
