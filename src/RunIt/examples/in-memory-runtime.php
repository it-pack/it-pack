<?php

declare(strict_types=1);

/**
 * RunIt — in-memory runtime.
 *
 * An "in-memory runtime" wires the SDK with `InMemoryArtifactPublisher`, so every emitted artifact
 * is retained and can be inspected afterwards. This is the same wiring the test suite uses.
 *
 * Run from the package root: `php examples/in-memory-runtime.php`
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
    ->build();

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
