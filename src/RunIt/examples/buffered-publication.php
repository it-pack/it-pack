<?php

declare(strict_types=1);

/**
 * RunIt — buffered publication, overflow strategies, and health reporting.
 *
 * Phase 3 feature: `BufferedArtifactPublisher` bounds the artifact buffer by item
 * count and estimated bytes. When it fills, the configured {@see OverflowStrategy}
 * decides what to drop: `DropOldest`, `DropNewest`, `DropLowPriority`,
 * `BlockWithTimeout`, or `Fail`. `flush()` emits a bounded batch; `close()` flushes
 * within the shutdown budget then drops and reports the remainder through the
 * internal health reporter — never recursing back into the pipeline.
 *
 * Run from the package root: `php examples/buffered-publication.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferOverflowException;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;

final class DroppingReporter implements InternalHealthReporterInterface
{
    /** @var list<array{string, ?string}> */
    public array $reported = [];

    public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        $this->reported[] = [$error->getMessage(), $artifact->normalize()['id'] ?? null];
    }
}

echo "=== Buffered publication: DropOldest overflow ===\n";

$reporter = new DroppingReporter();
$buffer = new BufferedArtifactPublisher(
    new BufferConfiguration(
        maxItems: 3,
        maxEstimatedBytes: 1_000,
        maxAgeSeconds: 60,
        batchSize: 2,
        flushIntervalSeconds: 0,
        shutdownFlushBudget: 2,
        overflow: OverflowStrategy::DropOldest,
    ),
    new InMemoryArtifactPublisher(),
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
    $reporter,
);

$artifacts = [];
for ($i = 1; $i <= 5; ++$i) {
    $artifacts[] = Observation::info(
        ObservationId::fromString("obs-$i"),
        ExecutionId::fromString('exec-1'),
        "observation $i",
    );
    $buffer->publish($artifacts[array_key_last($artifacts)]);
}

echo 'buffered: ' . $buffer->bufferedCount() . PHP_EOL;
echo 'dropped so far: ' . $buffer->droppedCount() . PHP_EOL;

// Health reporter saw the two oldest artifacts dropped to make room.
echo 'reported drops: ' . count($reporter->reported) . PHP_EOL;
foreach ($reporter->reported as [$message, $id]) {
    echo "  - $message -> $id\n";
}

// Flush emits up to batchSize artifacts.
$buffer->flush();
echo 'after flush buffered: ' . $buffer->bufferedCount() . PHP_EOL;

// close() flushes within the shutdown budget, then drops and reports the remainder.
$buffer->close();
echo 'after close buffered: ' . $buffer->bufferedCount() . PHP_EOL;
echo 'total dropped: ' . $buffer->droppedCount() . PHP_EOL;

echo PHP_EOL . "=== Default failure policy on buffer overflow ===\n";

// PublicationPipeline wraps its inner publisher in FailSafeArtifactPublisher with
// FailOpenPolicy when no failurePolicy is supplied: a transport/buffer error is
// contained and the business result is preserved. This mirrors that semantics with
// FailSafeArtifactPublisher directly (no provenance ceremony needed here).
$sink = new InMemoryArtifactPublisher();
$buffer = new BufferedArtifactPublisher(
    new BufferConfiguration(
        maxItems: 1,
        maxEstimatedBytes: 1_000,
        maxAgeSeconds: 60,
        batchSize: 1,
        flushIntervalSeconds: 0,
        shutdownFlushBudget: 1,
        overflow: OverflowStrategy::Fail,
    ),
    $sink,
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
);

// Default: fail-open — the overflowing publish does not throw.
$default = new FailSafeArtifactPublisher($buffer, new FailOpenPolicy());
$first = Observation::info(ObservationId::fromString('a'), ExecutionId::fromString('exec-1'), 'first');
$second = Observation::info(ObservationId::fromString('b'), ExecutionId::fromString('exec-1'), 'second');

$default->publish($first);
echo 'buffered first artifact: ' . $buffer->bufferedCount() . ' buffered, ' . count($sink->published()) . ' delivered' . PHP_EOL;

$threw = false;
try {
    $default->publish($second); // buffer is full; Fail strategy throws, FailOpen swallows
} catch (BufferOverflowException) {
    $threw = true;
}

echo 'default fail-open rethrew overflow: ' . ($threw ? 'yes (unexpected)' : 'no (contained)') . PHP_EOL;

$default->flush();
echo 'after flush delivered: ' . count($sink->published()) . ' (first artifact arrives)' . PHP_EOL;

// Explicit fail-closed — fill the buffer, then the overflowing publish rethrows.
$failClosed = new FailSafeArtifactPublisher($buffer, new FailClosedPolicy());
$filled = Observation::info(ObservationId::fromString('c'), ExecutionId::fromString('exec-1'), 'filled');
$failClosed->publish($filled); // buffer now full again
$third = Observation::info(ObservationId::fromString('d'), ExecutionId::fromString('exec-1'), 'third');

$threw = false;
try {
    $failClosed->publish($third); // buffer full; Fail strategy throws, FailClosed rethrows
} catch (BufferOverflowException) {
    $threw = true;
}

echo 'fail-closed rethrows overflow: ' . ($threw ? 'yes' : 'no (unexpected)') . PHP_EOL;

echo 'buffered publication complete' . PHP_EOL;
