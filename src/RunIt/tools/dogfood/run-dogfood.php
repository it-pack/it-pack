<?php

declare(strict_types=1);

/**
 * RunIt — dogfooding entry point.
 *
 * Runs the project's own dev-tooling (tests, static analysis, architecture,
 * mutation, benchmarks, release validation) as RunIt executions. Each tool runs in
 * a subprocess with its own bootstrap, so this process never instruments the
 * measured tool: the bootstrap path stays intentionally uninstrumented.
 *
 * Run from the package root: `composer dogfood`
 */

require __DIR__ . '/../../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\RecordingBatchFlushObserver;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Tools\Dogfood\Process\ShellProcessRunner;
use Tools\Dogfood\Run\DogfoodRunner;
use Tools\Dogfood\Tool\PredefinedTools;

$workingDirectory = dirname(__DIR__, 2);

$recorder = new InMemoryArtifactPublisher();
$flushObserver = new RecordingBatchFlushObserver();

$publisher = new BufferedArtifactPublisher(
    BufferConfiguration::default(),
    $recorder,
    null,
    null,
    null,
    $flushObserver,
);

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runner = new DogfoodRunner(
    $runIt,
    new ShellProcessRunner(),
    $publisher,
    $workingDirectory,
);

$report = $runner->runAll(PredefinedTools::all());

echo $report->render() . PHP_EOL;
echo sprintf(
    'flush self-instrumentation: %d flushes, %d artifacts flushed, max batch %d' . PHP_EOL,
    $flushObserver->flushCount(),
    $flushObserver->totalFlushed(),
    $flushObserver->maxBatch(),
);

exit($report->exitCode());
