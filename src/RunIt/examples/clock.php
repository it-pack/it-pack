<?php

declare(strict_types=1);

/**
 * RunIt SDK quick start.
 *
 * A few lines instrument existing code without modifying it. The SDK builder is adapter-agnostic:
 * you supply the port implementations (publisher, clock, sequence allocator, snapshot serializer,
 * and identifier generator) and RunIt wires the rest.
 *
 * Run from the package root: `php examples/sdk.php`
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

$clock = new SystemMonotonicClock();

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock($clock)
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;



$definition = ExecutionDefinition::create(
    ExecutionName::fromString('checkout.process'),
    ExecutionKind::fromString('operation')
);

$iterations = 10000;

$start = $clock->monotonicNanoseconds();

for ($i = 0; $i < $iterations; $i++) {

    $runtime->run(
        $definition,
        static fn (): array => ['status' => 'ok'],
    );

}

$stop = $clock->monotonicNanoseconds();
$time = $clock->measure($start, $stop);

echo sprintf("\nExecution took %d ns", ($time->nanoseconds() / $iterations / 1_000));
