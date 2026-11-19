<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — health reporter fallback.
 *
 * Phase 2 feature: When the health reporter throws, RunIt falls back to fixed
 * payload-free diagnostics via error_log. No exception messages or artifact
 * identifiers leak through the fallback path.
 *
 * This example shows:
 *   1. A throwing publication failure handler — the health reporter (a
 *      CallbackHealthReporter wrapping it) throws when reporting.
 *   2. FailSafeArtifactPublisher contains the transport fault; the original
 *      business result is preserved.
 *   3. Fallback diagnostics are payload-free — no exception messages or identifiers.
 *
 * Run from the package root: `php examples/health-reporter-fallback.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class FailingPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new RuntimeException('transport unavailable');
    }
}

$publisher = new FailSafeArtifactPublisher(
    new FailingPublisher(),
    new FailClosedPolicy(),
);

$publicationFailures = [];
$failureHandler = static function (Throwable $failure) use (&$publicationFailures): void {
    $publicationFailures[] = $failure->getMessage();
    // The handler throws — the CallbackHealthReporter wrapping it will throw too,
    // triggering the fallback path.
    throw $failure;
};

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublicationFailureHandler($failureHandler)
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('health.op'), static function (ExecutionScopeInterface $scope): void {
    echo 'execution ran despite publication failure' . "\n";
    echo 'fallback contains publication failure — no identifiers leaked' . "\n";
});

echo 'health reporter fallback complete' . "\n";
echo 'publication failures contained: ' . count($publicationFailures) . "\n";
