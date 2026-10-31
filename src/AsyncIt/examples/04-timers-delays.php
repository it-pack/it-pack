<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('timers-delays');
$clock = $fixture['clock'];

echo "=== awaitDelay with monotonic Duration ===\n";

// Durations use monotonic clocks (hrtime), never wall-clock, so durations are
// free of wall-clock skew (conformance Assertion B).
$runtime->run($scope, static function () use ($runtime): void {
    $start = hrtime(as_number: true);

    $runtime->awaitDelay(Duration::ofMilliseconds(50));
    $elapsed1 = hrtime(as_number: true) - $start;

    $runtime->awaitDelay(Duration::ofSeconds(0));
    $elapsed2 = hrtime(as_number: true) - $start;

    $runtime->awaitDelay(Duration::ofNanoseconds(5_000_000));
    $elapsed3 = hrtime(as_number: true) - $start;

    echo '  delay(50ms)   elapsed: ', number_format($elapsed1 / 1_000_000, 2), " ms\n";
    echo '  delay(0s)     elapsed: ', number_format($elapsed2 / 1_000_000, 2), " ms\n";
    echo '  delay(5ms)    elapsed: ', number_format($elapsed3 / 1_000_000, 2), " ms\n";
});

echo "\n=== Deterministic clock for exact timing ===\n";

// The DeterministicMonotonicClock only advances when advance() is called,
// enabling exact, sleep-free timing scenarios in tests and simulations.
$deterministicClock = new DeterministicMonotonicClock();

echo '  initial monotonic: ', $deterministicClock->monotonicNanoseconds(), " ns\n";
$deterministicClock->advance(1_000_000_000);
echo '  after +1s:        ', $deterministicClock->monotonicNanoseconds(), " ns\n";
$deterministicClock->advance(500_000_000);
echo '  after +0.5s:      ', $deterministicClock->monotonicNanoseconds(), " ns\n";

echo "\n=== Delayed task sequencing ===\n";

// Tasks scheduled with awaitDelay resume in deadline order. The scheduler's
// timer heap fires the nearest deadline first.
$order = [];
$runtime->run($scope, static function () use ($runtime, &$order): void {
    // Schedule tasks with staggered deadlines; the longest delay is spawned
    // first but resumes last because the timer heap orders by deadline.
    $runtime->spawn(static function () use ($runtime, &$order): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(300_000));
        $order[] = 'c';
    });

    $runtime->spawn(static function () use ($runtime, &$order): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(100_000));
        $order[] = 'a';
    });

    $runtime->spawn(static function () use ($runtime, &$order): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(200_000));
        $order[] = 'b';
    });

    // Wait for all three to complete (longest delay is 300µs).
    $runtime->awaitDelay(Duration::ofNanoseconds(500_000));
});

echo '  resume order: ', implode(' -> ', $order), "\n";
echo "  (expected: a -> b -> c, ordered by timer deadline)\n";

echo "\n=== Task status after delay ===\n";
$status = $runtime->run($scope, static function () use ($runtime): string {
    $task = $runtime->spawn(static function () use ($runtime): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(100_000));
    });

    return $task->status()->value;
});

echo '  delayed task status: ', $status, "\n";
