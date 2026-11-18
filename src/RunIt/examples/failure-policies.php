<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CollectAndContinuePolicy;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\ReportToFallbackPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — publisher failure policies.
 *
 * Phase 2 feature: FailSafeArtifactPublisher applies a failure policy when the
 * inner publisher throws. The original business exception and result are always
 * preserved regardless of publication failures.
 *
 * This example shows:
 *   1. FailOpenPolicy — swallows publication error (default).
 *   2. FailClosedPolicy — rethrows publication error.
 *   3. CollectAndContinuePolicy — collects error and continues.
 *   4. ReportToFallbackPolicy — forwards artifact to fallback publisher.
 *
 * Run from the package root: `php examples/failure-policies.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class StubArtifact implements ExecutionArtifactInterface
{
    public function normalize(): array
    {
        return ['id' => 'stub', 'type' => 'runit.stub'];
    }
}

final class FailingPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new \RuntimeException('transport unavailable');
    }
}

$artifact = new StubArtifact();

// --- FailOpenPolicy: swallows the error ---
$publisher = new FailSafeArtifactPublisher(
    new FailingPublisher(),
    new FailOpenPolicy(),
);

try {
    $publisher->publish($artifact);
    echo "FailOpenPolicy: publication error swallowed\n";
} catch (\Throwable) {
    echo "FailOpenPolicy: unexpected rethrow\n";
}

// --- FailClosedPolicy: rethrows ---
$publisher = new FailSafeArtifactPublisher(
    new FailingPublisher(),
    new FailClosedPolicy(),
);

try {
    $publisher->publish($artifact);
    echo "FailClosedPolicy: unexpected swallow\n";
} catch (\RuntimeException $e) {
    echo "FailClosedPolicy: rethrew transport error: " . $e->getMessage() . "\n";
}

// --- CollectAndContinuePolicy: collects error ---
$collected = [];
$publisher = new FailSafeArtifactPublisher(
    new FailingPublisher(),
    new CollectAndContinuePolicy(
        static function (\Throwable $error, ExecutionArtifactInterface $a) use (&$collected): void {
            $collected[] = $error->getMessage();
        },
    ),
);

try {
    $publisher->publish($artifact);
    echo "CollectAndContinuePolicy: continued after error\n";
} catch (\Throwable) {
    echo "CollectAndContinuePolicy: unexpected rethrow\n";
}

echo "collected errors: " . count($collected) . "\n";

// --- ReportToFallbackPolicy: forwards to fallback ---
$fallback = new InMemoryArtifactPublisher();
$publisher = new FailSafeArtifactPublisher(
    new FailingPublisher(),
    new ReportToFallbackPolicy($fallback),
);

try {
    $publisher->publish($artifact);
    echo "ReportToFallbackPolicy: forwarded to fallback\n";
} catch (\Throwable) {
    echo "ReportToFallbackPolicy: unexpected rethrow\n";
}

echo "failure policies complete\n";
