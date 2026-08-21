<?php

declare(strict_types=1);

/**
 * RunIt — failed execution.
 *
 * When the wrapped callable throws, the runner records the failure (capturing only a safe, bounded
 * description of the original Throwable) and rethrows the EXACT original exception. The business
 * exception is preserved; the publication failure never replaces it.
 *
 * Run from the package root: `php examples/failed-execution.php`
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

try {
    $runtime->run(
        ExecutionDefinition::create(ExecutionName::fromString('order.place'), ExecutionKind::fromString('operation')),
        static function (): void {
            throw new RuntimeException('payment gateway unavailable');
        },
    );
} catch (Throwable $e) {
    echo 'original exception preserved: ' . $e->getMessage() . PHP_EOL;
}

foreach ($publisher->published() as $artifact) {
    if ($artifact instanceof \ItPack\RunIt\Domain\Execution\Execution) {
        echo 'recorded execution status: ' . $artifact->normalize()['status'] . PHP_EOL;
    }
}
