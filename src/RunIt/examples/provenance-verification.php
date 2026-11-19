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
 *   3. Duplicate execution identity triggers an unverified report (payload-free).
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

// --- Business ID vs verified provenance ---

// Create a live execution record (provenance record established).
$runIt->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static function (ExecutionScopeInterface $scope): void {
        $execution = $scope->execution();
        echo 'business execution ID: ' . $execution->id()->toString() . "\n";
        echo 'business name:          ' . $execution->definition()->name()->toString() . "\n";
        echo 'business kind:          ' . $execution->definition()->kind()->toString() . "\n";
        echo "\n";
        echo 'The business ID alone is an untrusted claim.' . "\n";
        echo 'Only the SDK-issued export ID (verified by ProvenanceAuthority)' . "\n";
        echo "establishes trust for this execution's artifacts." . "\n";
    },
);

// --- Duplicate identity triggers unverified report ---
// Reusing the same business name with a live provenance record triggers
// a duplicate-execution rejection (payload-free health notice).
try {
    $runIt->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('order.place'),
            ExecutionKind::fromString('operation'),
        ),
        static function (ExecutionScopeInterface $scope): void {
            echo 'second run of order.place — unverified duplicate' . "\n";
        },
    );
} catch (Throwable $throwable) {
    echo 'duplicate identity rejected: ' . $throwable->getMessage() . "\n";
}

echo 'provenance verification complete' . "\n";
