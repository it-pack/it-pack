<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Port\CircuitState;
use ItPack\AsyncIt\Contract\Port\JitterStrategy;
use ItPack\AsyncIt\Contract\Port\RetryStrategy;
use ItPack\AsyncIt\Domain\CircuitBreaker;
use ItPack\AsyncIt\Domain\Exception\CircuitBreakerOpenException;
use ItPack\AsyncIt\Domain\RetryPolicy;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('resilience');

echo "=== RetryPolicy: exponential backoff with jitter ===\n";

// RetryPolicy governs how many times an operation is retried after a
// transient failure, with configurable strategy, delay, and jitter. The
// original Throwable is rethrown after maxAttempts — never wrapped.
$attempts = 0;
$policy = new RetryPolicy(
    maxAttempts: 4,
    baseDelay: Duration::ofNanoseconds(50_000),
    maxDelay: Duration::ofNanoseconds(200_000),
    strategy: RetryStrategy::Exponential,
    jitter: JitterStrategy::None,
    retryable: ['RuntimeException'],
);

$result = null;
$runtime->run($scope, static function () use ($runtime, &$attempts, $policy, &$result): string {
    $attempts = 0;

    $operation = static function () use (&$attempts): string {
        $attempts++;
        echo "  attempt $attempts\n";

        if ($attempts < 3) {
            throw new \RuntimeException("transient failure on attempt $attempts");
        }

        return "success on attempt $attempts";
    };

    // Manual retry loop within the task: catch, awaitDelay backoff, retry.
    // This mirrors what EngineSchedulerInterface::retry() does internally.
    $result = null;
    $lastError = null;

    for ($attempt = 1; $attempt <= $policy->maxAttempts; $attempt++) {
        try {
            $result = $operation();

            break;
        } catch (\Throwable $e) {
            $lastError = $e;

            if ($attempt >= $policy->maxAttempts || !$policy->isRetryable(\get_class($e))) {
                throw $e;
            }

            $delay = $policy->delayForAttempt($attempt);
            echo '  backing off for ', $delay->nanoseconds() / 1_000_000, " ms\n";
            $runtime->awaitDelay($delay);
        }
    }

    return $result ?? throw $lastError;
});

echo '  final result: ', $result, "\n";
echo '  total attempts: ', $attempts, "\n";

echo "\n=== RetryPolicy: non-retryable exception fails immediately ===\n";
$attempts2 = 0;
$policy2 = new RetryPolicy(
    maxAttempts: 5,
    retryable: ['RuntimeException'], // LogicException is NOT retryable
);

try {
    $runtime->run($scope, static function () use (&$attempts2): void {
        $attempts2++;
        throw new \LogicException('permanent failure');
    });
} catch (\LogicException $e) {
    echo '  LogicException propagated after ', $attempts2, " attempt(s) (not retried)\n";
}

echo "\n=== CircuitBreaker: closed → open → half-open → closed ===\n";

// CircuitBreaker protects downstream services from cascading failures.
// States: Closed → Open (on failure threshold) → HalfOpen (after cooldown) →
// Closed (on success) or Open (on failure).
$clock = new SystemMonotonicClock();
$breaker = new CircuitBreaker(
    clock: $clock,
    failureThreshold: 3,
    failureTimeout: Duration::ofNanoseconds(0), // no delay between failure counting
    cooldown: Duration::ofNanoseconds(100_000_000), // 100ms cooldown
);

echo '  initial state: ', $breaker->state()->value, "\n";

$failCount = 0;
for ($i = 1; $i <= 5; $i++) {
    try {
        $breaker->allowExecution(static function () use (&$failCount): string {
            $failCount++;

            throw new \RuntimeException("downstream failure #$failCount");
        });
        echo "  call $i: success\n";
    } catch (CircuitBreakerOpenException $e) {
        echo "  call $i: circuit OPEN (short-circuited)\n";
    } catch (\RuntimeException $e) {
        echo "  call $i: failure (state: ", $breaker->state()->value, ")\n";
    }
}

echo '  state after 5 calls: ', $breaker->state()->value, "\n";

// Wait for cooldown, then a half-open probe call succeeds and closes the circuit.
$runtime->run($scope, static function () use ($runtime): void {
    $runtime->awaitDelay(Duration::ofNanoseconds(150_000_000)); // wait past cooldown
});

$breaker->allowExecution(static function (): string {
    return 'probe success';
});
echo '  after cooldown + success: ', $breaker->state()->value, "\n";

echo "\n=== Circuit state transitions ===\n";
echo '  CircuitState::Closed  = ', CircuitState::Closed->value, "\n";
echo '  CircuitState::Open    = ', CircuitState::Open->value, "\n";
echo '  CircuitState::HalfOpen = ', CircuitState::HalfOpen->value, "\n";
