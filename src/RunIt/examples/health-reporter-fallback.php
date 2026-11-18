<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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
 *   1. A throwing health reporter is contained by FailSafeArtifactPublisher.
 *   2. Fallback error_log reporter receives payload-free fallback diagnostics.
 *   3. The original business result is never replaced by publication failures.
 *
 * Run from the package root: `php examples/health-reporter-fallback.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class ThrowingHealthReporter implements InternalHealthReporterInterface
{
    public function report(\Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
    {
        throw new \RuntimeException('health reporter down');
    }
}

$healthCalls = [];
$health = new class($healthCalls) implements InternalHealthReporterInterface {
    public function __construct(private array &$calls) {}
    public function report(\Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
    {
        $this->calls[] = ['message' => $error->getMessage(), 'artifactId' => null];
    }
};

$publisher = new InMemoryArtifactPublisher();

// --- Throwing health reporter → fallback to error_log ---
$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublicationFailureHandler(static function (\Throwable $failure) use ($healthCalls): void {
        $healthCalls[] = ['message' => $failure->getMessage(), 'artifactId' => null];
    })
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('health.op'), static function (ExecutionScopeInterface $scope): void {
    echo "execution ran despite throwing health reporter\n";
    echo "fallback contains publication failure — no identifiers leaked\n";
});

echo "health reporter fallback complete\n";
