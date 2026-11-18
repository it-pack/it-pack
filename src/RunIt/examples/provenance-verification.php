<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — provenance verification: export IDs vs business IDs.
 *
 * Phase 2 feature: Only locally-issued, verified export IDs count as trust.
 * Business-facing IDs (execution name/kind) are untrusted claims. Every artifact
 * is bound to a verified export ID before publication; foreign or business-only
 * identities cannot forge provenance.
 *
 * This example shows:
 *   1. The business execution ID is separate from the verified export ID.
 *   2. Artifacts recorded through a scope are bound to that scope's export ID.
 *   3. Duplicate execution identity is rejected (payload-free unverified report).
 *
 * Run from the package root: `php examples/provenance-verification.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

// --- Business ID vs verified provenance ---

$runIt->run($definition('order.place'), static function (ExecutionScopeInterface $scope): void {
    $execution = $scope->execution();
    echo "business execution ID: " . $execution->id()->toString() . "\n";
    echo "business name:          " . $execution->definition()->name()->toString() . "\n";
    echo "business kind:          " . $execution->definition()->kind()->toString() . "\n";
    echo "\n";
    echo "The business ID alone is an untrusted claim.\n";
    echo "Only the SDK-issued export ID (verified by ProvenanceAuthority)\n";
    echo "establishes trust for this execution's artifacts.\n";
});

// --- Duplicate identity triggers unverified report ---

try {
    $runIt->run($definition('duplicate.id'), static function (ExecutionScopeInterface $scope): void {
        echo "first run of duplicate.id recorded\n";
    });
    $runIt->run($definition('duplicate.id'), static function (ExecutionScopeInterface $scope): void {
        echo "second run of duplicate.id — unverified\n";
    });
} catch (\Throwable $e) {
    echo "duplicate identity rejected: " . $e->getMessage() . "\n";
}

echo "provenance verification complete\n";
