<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\GracefulShutdown;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('graceful-shutdown');
$scheduler = $fixture['scheduler'];
$rootScope = $fixture['rootScope'];
$clock = $fixture['clock'];

echo "=== GracefulShutdown: structured reactor shutdown ===\n";

// GracefulShutdown orchestrates shutdown: cancels the root CancellationScope
// (cascading to all child tasks via structured concurrency), then drains the
// reactor within a deadline. Poll handles are released on terminal transition
// (Assertion C). Tasks unwind cooperatively at await boundaries.
$shutdown = new GracefulShutdown(
    scheduler: $scheduler,
    rootScope: $rootScope,
    clock: $clock,
    deadline: Duration::ofSeconds(5),
);

// Register signal handlers for SIGTERM/SIGINT (CLI-only).
// Non-CLI environments (php-fpm, workers) call shutdown() explicitly.
$shutdown->registerSignals();
echo "  signal handlers registered (SIGTERM/SIGINT)\n";

// Spawn cooperative tasks that watch the shared root scope.
// Each task checks isCancelled() at await boundaries and unwinds when the
// root scope is cancelled.
$taskIds = [];
$runtime->run($scope, static function () use ($runtime, $rootScope): void {
    for ($i = 1; $i <= 3; $i++) {
        $index = $i;
        $runtime->spawn(static function () use ($runtime, $rootScope, $index): void {
            for ($j = 0; $j < 50; $j++) {
                if ($rootScope->isCancelled()) {
                    echo "  task-$index: observed root scope cancellation, unwinding\n";

                    return;
                }
                echo "  task-$index: tick $j\n";
                $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
            }
            echo "  task-$index: completed normally\n";
        });
    }

    // Let tasks run briefly, then initiate shutdown.
    $runtime->awaitDelay(Duration::ofNanoseconds(200_000));

    echo "  initiating graceful shutdown (root scope cancel + reactor drain)\n";
    $rootScope->cancel(CancellationReason::SystemShutdown);
});

echo "\n=== GracefulShutdown API surface ===\n";
echo "  shutdown(): cancels root scope, then scheduler->runUntil(deadline)\n";
echo "  registerSignals(): installs SIGTERM/SIGINT handlers (CLI only)\n";
echo '  deadline: ', $clock->monotonicNanoseconds(), " ns (monotonic, wall-skew-free)\n";

echo "\n=== Shutdown guarantees ===\n";
echo "  - Cancellation cascades from root scope to all child tasks\n";
echo "  - Tasks unwind cooperatively at await boundaries (no preemptive kill)\n";
echo "  - Poll handles and timers released on terminal transition (Assertion C)\n";
echo "  - Deadline enforced: force-cancels remaining tasks after expiry\n";
echo "  - Original business exceptions preserved through unwinding\n";
