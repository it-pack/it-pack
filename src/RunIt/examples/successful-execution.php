<?php

declare(strict_types=1);

use ItPack\RunIt\Domain\Execution\Execution;

/**
 * RunIt — successful execution.
 *
 * The simplest end-to-end shape: one execution wrapping a callable whose return value passes through
 * unchanged. On success the runner records a terminal snapshot and rethrows nothing.
 *
 * Run from the package root: `php examples/successful-execution.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$publisher = new InMemoryArtifactPublisher();

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$result = $runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('order.place'), ExecutionKind::fromString('operation')),
    static fn (): array => ['order_id' => 42, 'status' => 'paid'],
);

echo 'execution succeeded; result: ' . json_encode($result) . PHP_EOL;

$execution = null;
foreach ($publisher->published() as $artifact) {
    if ($artifact instanceof Execution) {
        $execution = $artifact;
    }
}

echo 'recorded execution status: ' . ($execution instanceof Execution ? $execution->normalize()['status'] : 'unknown') . PHP_EOL;
