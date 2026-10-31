<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Federation\CanaryScheduler;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\SchedulerConfiguration;
use ItPack\AsyncIt\Domain\Task;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;

$fixture = makeReferenceRuntime();
$scheduler = $fixture['scheduler'];

echo "=== Canary Deployments + Hot Config Swap (A.7) ===\n";

// A.7.1 — SchedulerConfiguration::forCanary() builds a canary profile from
// defaults() so every non-canary field inherits baseline values, then applies
// only the canary weight. Invalid weights throw.
$canaryConfig = SchedulerConfiguration::forCanary(0.3);
echo '  canary weight (forCanary 0.3): ', $canaryConfig->canaryWeight ?? 'null', "\n";

// A.7.1 — Hot config swap: withChanges() produces a new immutable snapshot.
// Unknown keys are rejected at swap time — never silently ignored.
try {
    $canaryConfig->withChanges(['unknownKey' => 'value']);
    echo "  ERROR: unknown key should have thrown\n";
} catch (DomainException $domainException) {
    echo '  withChanges rejected unknown key: ', $domainException->getMessage(), "\n";
}

// Swap telemetry sampling rate atomically (hot reload).
$hotConfig = $canaryConfig->withChanges(['telemetrySamplingRate' => 0.5]);
echo '  telemetrySamplingRate after swap: ', $hotConfig->telemetrySamplingRate, "\n";
echo '  canaryWeight preserved after swap: ', $hotConfig->canaryWeight ?? 'null', "\n";
echo '  original config unchanged: ', $canaryConfig->telemetrySamplingRate, "\n";

// A.7.2 — CanaryScheduler wraps a baseline and canary scheduler, routing a
// fraction of dispatches to the canary. Tracks error/latency metrics for both
// paths and auto-rolls-back to 100% baseline on threshold breach.
// Uses cryptographically secure random_int() for traffic-splitting decisions.
$canary = new CanaryScheduler(
    baseline: $scheduler,
    canary: $scheduler,
    canaryWeight: 0.5,
    errorMargin: 0.1,
    latencyFactor: 2.0,
    minCalls: 3,
);

echo '  canary weight: ', $canary->canaryWeight(), "\n";
echo '  rolled back initially: ', ($canary->isRolledBack() ? 'yes' : 'no'), "\n";

// Dispatch several successful calls to canary and baseline to populate metrics.
// Each dispatch uses a fresh Task (tasks are terminal after one completion).
for ($i = 0; $i < 10; $i++) {
    $task = new Task(
        new TaskId("canary-ok-$i"),
        ExecutionId::fromString('canary-example'),
        new CancellationScope(),
    );
    try {
        $canary->dispatch($task, static fn (): string => 'ok');
    } catch (Throwable) {
        // ignore — metrics still recorded
    }
}

$metrics = $canary->metrics();
echo '  baseline calls: ', $metrics['baseline']['calls'], ', canary calls: ', $metrics['canary']['calls'], "\n";

// Force canary errors to exceed the error margin and trigger rollback.
// Uses weight 0.5 so both baseline and canary receive calls; rollback
// triggers when the canary's error rate exceeds baseline + errorMargin.
// The result depends on the CSPRNG draw — rollback is conditional, not
// guaranteed on every run.
for ($i = 0; $i < 10; $i++) {
    $task = new Task(
        new TaskId("canary-fail-$i"),
        ExecutionId::fromString('canary-example'),
        new CancellationScope(),
    );
    try {
        $canary->dispatch($task, static function (): string {
            throw new RuntimeException('canary failure');
        });
    } catch (RuntimeException) {
        // expected — canary path throws
    }
}

echo '  rolled back after canary failures: ', ($canary->isRolledBack() ? 'yes (100% baseline)' : 'no (metrics below threshold)'), "\n";

// A.7.3 — Hot config swap / roll-forward: resetRollback() re-enables the
// canary path without a process restart.
$canary->resetRollback();
echo '  rolled back after reset: ', ($canary->isRolledBack() ? 'yes' : 'no (re-enabled)'), "\n";

// A.7.2 — Invalid constructor arguments are rejected.
try {
    new CanaryScheduler(
        baseline: $scheduler,
        canary: $scheduler,
        canaryWeight: 1.5,
        errorMargin: 0.1,
        latencyFactor: 2.0,
        minCalls: 3,
    );
    echo "  ERROR: invalid weight should have thrown\n";
} catch (InvalidArgumentException $invalidArgumentException) {
    echo '  invalid canaryWeight rejected: ', $invalidArgumentException->getMessage(), "\n";
}

echo "\n=== Canary Deployment patterns demonstrated ===\n";
echo "  - SchedulerConfiguration::forCanary() builds from defaults() + withChanges()\n";
echo "  - withChanges() rejects unknown keys (fail-fast hot config swap)\n";
echo "  - CanaryScheduler routes weighted traffic with random_int() (CSPRNG)\n";
echo "  - Relative error-rate / latency rollback vs baseline\n";
echo "  - resetRollback() enables roll-forward without restart\n";
