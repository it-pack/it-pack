<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;
use ItPack\AsyncIt\Domain\Bulkhead;
use ItPack\AsyncIt\Domain\RateLimiter;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('bulkhead-rate-limiter');

echo "=== Bulkhead: concurrency isolation ===\n";

// Bulkhead limits concurrent operations (semaphore-style). tryAcquire()
// reserves a slot or throws BulkheadFullException; release() frees it.
// Used to prevent one downstream dependency from exhausting all resources.
$bulkhead = new Bulkhead(capacity: 2);
$active = 0;
$peak = 0;

$runtime->run($scope, static function () use ($runtime, $bulkhead, &$active, &$peak): void {
    $tasks = [];

    for ($i = 1; $i <= 5; $i++) {
        $index = $i;
        $tasks[] = $runtime->spawn(static function () use ($runtime, $bulkhead, $index, &$active, &$peak): string {
            try {
                $bulkhead->tryAcquire();
            } catch (BulkheadFullException) {
                return "task-$index: rejected (bulkhead full)";
            }

            try {
                $active++;
                if ($active > $peak) {
                    $peak = $active;
                }

                echo "  task-$index: acquired (active=$active)\n";
                $runtime->awaitDelay(Duration::ofNanoseconds(200_000));

                return "task-$index: done";
            } finally {
                $active--;
                $bulkhead->release();
                echo "  task-$index: released (active=$active)\n";
            }
        });
    }

    $results = [];
    foreach ($tasks as $task) {
        $results[] = $runtime->await($task);
    }

    echo '  peak concurrency: ', $peak, " (capacity: 2)\n";
    echo '  results: ', implode('; ', $results), "\n";
});

echo "\n=== Bulkhead waitForCapacity() pattern ===\n";
// waitForCapacity() returns true if a slot was acquired, false if at capacity.
// Callers suspend and retry — useful in cooperative loops.
$bulkhead2 = new Bulkhead(capacity: 1);
$acquired2 = 0;
$gaveUp2 = 0;

$runtime->run($scope, static function () use ($runtime, $bulkhead2, &$acquired2, &$gaveUp2): void {
    $tasks = [];

    for ($i = 1; $i <= 3; $i++) {
        $index = $i;
        $tasks[] = $runtime->spawn(static function () use ($runtime, $bulkhead2, $index, &$acquired2, &$gaveUp2): string {
            $tries = 0;
            while (true) {
                $tries++;

                // waitForCapacity() returns true when a slot is acquired,
                // false when at capacity (caller suspends and retries).
                if ($bulkhead2->waitForCapacity()) {
                    $acquired2++;
                    $runtime->awaitDelay(Duration::ofNanoseconds(150_000));
                    $bulkhead2->release();

                    return "task-$index: acquired after $tries try(s)";
                }

                if ($tries > 3) {
                    $gaveUp2++;
                    return "task-$index: gave up after $tries try(s)";
                }

                // Yield briefly before retrying.
                $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
            }
        });
    }

    foreach ($tasks as $task) {
        echo '  ', $runtime->await($task), "\n";
    }

    echo '  acquired: ', $acquired2, ', gave up: ', $gaveUp2, "\n";
});

echo "\n=== RateLimiter: token bucket throttling ===\n";

// RateLimiter refills tokens at refillRatePerSecond up to capacity.
// tryConsume() takes one token immediately or returns false. waitForPermit()
// returns the delay needed before a token is available.
$clock = new SystemMonotonicClock();
$limiter = new RateLimiter(
    capacity: 3,
    refillRatePerSecond: 10, // 10 tokens/sec = 100ms per token
    clock: $clock,
);

$consumed = 0;
$delayed = 0;

for ($i = 1; $i <= 8; $i++) {
    if ($limiter->tryConsume()) {
        $consumed++;
        echo "  request $i: token available, consumed immediately\n";
    } else {
        $delayed++;
        $delay = $limiter->waitForPermit();
        echo "  request $i: rate-limited, would wait ", $delay->nanoseconds() / 1_000_000, " ms for permit\n";
        // In a Fiber task you would: $runtime->awaitDelay($delay); $limiter->consume();
    }
}

echo '  consumed immediately: ', $consumed, ', delayed: ', $delayed, "\n";

echo "\n=== RateLimiter with awaitDelay (cooperative throttling) ===\n";
$limiter2 = new RateLimiter(
    capacity: 2,
    refillRatePerSecond: 20,
    clock: $clock,
);

$runtime->run($scope, static function () use ($runtime, $limiter2): void {
    for ($i = 1; $i <= 5; $i++) {
        $delay = $limiter2->waitForPermit();
        if ($delay->nanoseconds() > 0) {
            echo "  request $i: awaiting permit (", $delay->nanoseconds() / 1_000_000, " ms)\n";
            $runtime->awaitDelay($delay);
        }

        $limiter2->consume();
        echo "  request $i: permit acquired, proceeding\n";
    }
});
