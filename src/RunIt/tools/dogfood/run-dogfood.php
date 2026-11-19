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
 * Phase 2 showcase: dogfood configures publication security end-to-end — a custom
 * redactor masks sensitive keys, security limits bound artifact data, and a
 * publication failure handler contains transport faults within the SDK publication
 * boundary handled by runTool() catch blocks. Flush() and close() failures are not
 * contained by this handler. Metrics are recorded as observations (not measurements)
 * so they pass through every classifier in the publication stack without triggering
 * `DefaultMeasurementClassifier`'s delivery-loss report.
 *
 * Run from the package root: `composer dogfood`
 */

require __DIR__ . '/../../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RecordingBatchFlushObserver;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Tools\Dogfood\Process\ShellProcessRunner;
use Tools\Dogfood\Run\DogfoodRunner;
use Tools\Dogfood\Tool\PredefinedTools;

$workingDirectory = dirname(__DIR__, 2);

$recorder = new InMemoryArtifactPublisher();
$flushObserver = new RecordingBatchFlushObserver();

// Phase 2: security limits bound artifact data before it can leave the process.
$limits = SecurityLimits::default()
    ->withMaxAttributeCount(8)
    ->withMaxAttributeValueBytes(256)
    ->withMaxSnapshotPayloadBytes(4096)
    ->withMaxOccurrencePayloadBytes(8192)
;

// Phase 2: custom redactor masks sensitive attribute/data keys before publication.
$redactor = new DefaultArtifactRedactor(
    new ArtifactRedactionConfig(
        sensitiveAttributeKeys: ['password', 'token', 'secret', 'api_key'],
        sensitiveDataKeys: ['password', 'token', 'secret'],
        marker: '[redacted]',
        redactThrowableMessages: true,
        redactOccurrenceData: true,
        redactSnapshotPayloads: true,
        redactObservationMessages: true,
        redactMeasurementValues: false,
    ),
);

// Phase 2: publication failure handler contains transport faults without
// replacing the tool outcome.
$publicationFailures = [];
$failureHandler = static function (\Throwable $failure) use (&$publicationFailures): void {
    $publicationFailures[] = $failure->getMessage();
};

$publisher = new BufferedArtifactPublisher(
    BufferConfiguration::default(),
    $recorder,
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
    null,
    $flushObserver,
);

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withRedactor($redactor)
    ->withSecurityLimits($limits)
    ->withPublicationFailureHandler($failureHandler)
    ->build()
;

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

if ($publicationFailures !== []) {
    echo sprintf(
        'publication failures contained: %d (original tool outcomes preserved)' . PHP_EOL,
        count($publicationFailures),
    );
}

exit($report->exitCode());
