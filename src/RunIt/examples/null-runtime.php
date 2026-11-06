<?php

declare(strict_types=1);

/**
 * RunIt — null runtime.
 *
 * A "null runtime" wires the SDK with no-op adapters (a publisher that discards every artifact) so
 * you can exercise the API without producing any telemetry. Useful for local trials and as a
 * baseline in tests.
 *
 * Run from the package root: `php examples/null-runtime.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Null\NullArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new NullArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$result = $runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static fn (): array => ['status' => 'ok'],
);

echo 'result: ' . json_encode($result) . PHP_EOL;
echo 'nothing was published (null runtime)' . PHP_EOL;
