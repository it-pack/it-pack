<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Exception\CancellationException;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('cancellation');

echo "=== Cooperative cancellation via CancellationScope ===\n";

// An application-owned CancellationScope is shared with a worker task through
// closure capture. The worker checks isCancelled() at each await boundary and
// unwinds gracefully when cancellation is observed. onCancel() registers
// cleanup that runs synchronously when cancel() is called.
$appScope = new CancellationScope();
$appScope->onCancel(static function (): void {
    echo "  onCancel: cleanup callback executed (synchronously on cancel())\n";
});

$runtime->run($scope, static function () use ($runtime, $appScope): void {
    $task = $runtime->spawn(static function () use ($appScope, $runtime): void {
        for ($i = 0; $i < 20; $i++) {
            if ($appScope->isCancelled()) {
                echo "  worker: cancellation observed at iteration $i, unwinding gracefully\n";

                return;
            }

            echo "  worker: iteration $i\n";
            $runtime->awaitDelay(Duration::ofNanoseconds(80_000));
        }

        echo "  worker: completed all iterations without cancellation\n";
    });

    echo "parent: letting worker run...\n";
    $runtime->awaitDelay(Duration::ofNanoseconds(400_000));

    echo "parent: cancelling application scope\n";
    $appScope->cancel(CancellationReason::UserInitiated);

    $result = $runtime->await($task);
    echo 'parent: worker returned: ', var_export($result, return: true), "\n";
});

echo "\n=== throwIfCancelled() pattern ===\n";

// throwIfCancelled() throws CancellationException when the scope is cancelled.
// The task catches it to perform final cleanup before unwinding. The original
// business exception is preserved — cancellation is an observed state change,
// never a preemptive kill.
$throwScope = new CancellationScope();
$throwScope->onCancel(static function (): void {
    echo "  onCancel: worker2 cleanup callback executed\n";
});

$runtime->run($scope, static function () use ($runtime, $throwScope): void {
    $task = $runtime->spawn(static function () use ($throwScope, $runtime): void {
        try {
            for ($i = 0; $i < 10; $i++) {
                $throwScope->throwIfCancelled();
                echo "  worker2: iteration $i\n";
                $runtime->awaitDelay(Duration::ofNanoseconds(80_000));
            }
        } catch (CancellationException) {
            echo "  worker2: caught CancellationException, performing cleanup\n";
        }

        echo "  worker2: unwound cleanly\n";
    });

    $runtime->awaitDelay(Duration::ofNanoseconds(250_000));
    $throwScope->cancel(CancellationReason::UserInitiated);

    $runtime->await($task);
    echo "parent2: worker2 unwound (no exception propagated to parent)\n";
});

echo "\n=== Cancellation reason inspection ===\n";
$reasonScope = new CancellationScope();
$reasonScope->cancel(CancellationReason::DeadlineExceeded);
echo 'reason: ', $reasonScope->reason()->value, "\n";
echo 'isCancelled: ', $reasonScope->isCancelled() ? 'true' : 'false', "\n";
