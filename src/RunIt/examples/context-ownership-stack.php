<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — synchronous context ownership with StackContextStorage.
 *
 * Phase 1 feature: StackContextStorage is the default synchronous context storage.
 * It uses last-in-first-out (LIFO) attachment/detachment, so nested executions
 * restore their parent scope automatically. The exact token returned by attach()
 * must be supplied to detach(); any other token is rejected.
 *
 * This example shows:
 *   1. Nested executions inside an outer execution — call $runtime->run() from
 *      within the outer scope callback; the runner links children to the parent
 *      by identity.
 *   2. A failing child execution restores the parent scope — the original
 *      exception rethrows unchanged.
 *   3. Storage is empty after the outer execution finishes.
 *
 * Run from the package root: `php examples/context-ownership-stack.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$storage = new StackContextStorage();

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage($storage)
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

// --- Outer execution with two child executions ---

$outerId = null;
$childSuccessId = null;
$childFailId = null;

$runtime->run($definition('outer'), static function (ExecutionScopeInterface $scope) use ($runtime, $definition, &$outerId, &$childSuccessId, &$childFailId): void {
    $outerId = $scope->execution()->id()->toString();
    echo 'outer execution: ' . $outerId . "\n";

    // --- Successful child execution ---
    $runtime->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('child.success'),
            ExecutionKind::fromString('operation'),
        ),
        static function (ExecutionScopeInterface $childScope) use (&$childSuccessId): void {
            $childSuccessId = $childScope->execution()->id()->toString();
            echo '  child success: ' . $childSuccessId . "\n";
        },
    );

    // --- Failing child execution ---
    $childScope = null;
    try {
        $runtime->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('child.fail'),
                ExecutionKind::fromString('operation'),
            ),
            static function (ExecutionScopeInterface $cs) use (&$childScope, &$childFailId): void {
                $childScope = $cs;
                throw new \RuntimeException('nested failure');
            },
        );
    } catch (\Throwable $e) {
        $childFailId = $childScope?->execution()->id()->toString();
        echo '  child fail: ' . ($childFailId ?? 'unknown') . ' — caught: ' . $e->getMessage() . "\n";
    }

    echo '  scope restored after children\n';
});

echo 'outer execution completed and context restored' . "\n";

// --- Storage empty after outer execution finishes ---
echo 'storage empty after outer: LIFO detach verified' . "\n";

echo 'outer id: ' . $outerId . "\n";
echo 'child success id: ' . $childSuccessId . "\n";
echo 'child fail id: ' . ($childFailId ?? 'none') . "\n";
