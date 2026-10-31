<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;
use ItPack\AsyncIt\Contract\Exception\ChannelEmptyException;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\Bulkhead;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('enterprise-workflow');

echo "=== Enterprise workflow: order processing pipeline ===\n";

// Scenario: process N orders concurrently. Each order goes through
// validate → charge → ship stages. Charges are bulkhead-isolated (max 2
// concurrent to the payment gateway). Results stream through a channel to a
// collector. Order 4 always fails, exhausts retries, and cancels the whole
// workflow via a shared CancellationScope.
//
// All scheduling primitives are invoked on $runtime (spawn, await, awaitDelay),
// which delegates to the active scheduler inside run().
$orderCount = 6;
$chargeBulkhead = new Bulkhead(capacity: 2);
$resultsChannel = new EmulatedChannel();
$workflowScope = new CancellationScope();
$workflowScope->onCancel(static function (): void {
    echo "  [workflow] root scope cancelled — all orders unwinding\n";
});

$runtime->run($scope, static function () use (
    $runtime,
    $orderCount,
    $chargeBulkhead,
    $resultsChannel,
    $workflowScope,
): void {
    // Collector task: drains the results channel until it is closed.
    $collector = $runtime->spawn(static function () use ($runtime, $resultsChannel): void {
        while (true) {
            try {
                $result = $resultsChannel->receive();
                echo "  [collector] $result\n";
            } catch (ChannelEmptyException) {
                if ($resultsChannel->isClosed()) {
                    break;
                }

                $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
            }
        }
    });

    // Fan-out: spawn one task per order, all sharing the workflow scope.
    $childTasks = [];

    for ($orderId = 1; $orderId <= $orderCount; $orderId++) {
        $id = $orderId;
        $childTasks[] = $runtime->spawn(static function () use (
            $runtime,
            $chargeBulkhead,
            $resultsChannel,
            $workflowScope,
            $id,
        ): void {
            $stages = ['validate', 'charge', 'ship'];

            foreach ($stages as $stage) {
                if ($workflowScope->isCancelled()) {
                    echo "  [order-$id] cancelled at $stage\n";

                    return;
                }

                echo "  [order-$id] $stage...\n";
                $runtime->awaitDelay(Duration::ofNanoseconds(100_000));

                // Charge stage: bulkhead-isolated, may fail transiently.
                if ($stage === 'charge') {
                    for ($attempt = 1; $attempt <= 3; $attempt++) {
                        if ($workflowScope->isCancelled()) {
                            return;
                        }

                        try {
                            $chargeBulkhead->tryAcquire();
                        } catch (BulkheadFullException $e) {
                            echo "  [order-$id] charge bulkhead full, retrying\n";
                            $runtime->awaitDelay(Duration::ofNanoseconds(100_000));
                            continue;
                        }

                        try {
                            // Order 4 always fails → exhausts retries → cancels workflow.
                            if ($id === 4) {
                                throw new RuntimeException('payment gateway timeout');
                            }

                            echo "  [order-$id] charge confirmed (attempt $attempt)\n";
                            break;
                        } catch (RuntimeException $e) {
                            echo "  [order-$id] charge attempt $attempt failed: ", $e->getMessage(), "\n";
                            if ($attempt >= 3) {
                                $workflowScope->cancel(CancellationReason::UserInitiated);
                                echo "  [order-$id] charge retries exhausted — cancelling workflow\n";

                                return;
                            }
                        } finally {
                            $chargeBulkhead->release();
                        }
                    }
                }
            }

            echo "  [order-$id] completed\n";
            $resultsChannel->send("order-$id: completed");
        });
    }

    // Await each order in spawn order. Suspended children unwind at their
    // next await boundary when the workflow scope is cancelled.
    foreach ($childTasks as $i => $child) {
        $runtime->await($child);
        echo '  [workflow] child ', $i + 1, " awaited\n";
    }

    $resultsChannel->close();
    $runtime->await($collector);

    echo "  [workflow] driver finished\n";
});

echo "\n=== Workflow patterns demonstrated ===\n";
echo "  - Structured concurrency: fan-out with shared CancellationScope\n";
echo "  - Bulkhead isolation for downstream resource protection\n";
echo "  - TaskChannel for result streaming between tasks\n";
echo "  - Cooperative cancellation at every await boundary\n";
echo "  - Retry with backoff for transient failures\n";
echo "  - Failure in one child cancels the entire workflow\n";
